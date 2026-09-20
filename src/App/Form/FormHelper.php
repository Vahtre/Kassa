<?php

namespace App\Form;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use App\Component\HttpFoundation\JSendResponse;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class FormHelper
{
    /**
     * Return all errors of the form and its children
     *
     * @param FormInterface $form
     * @param array $errors
     * @return array
     */
    public static function getErrors(FormInterface $form, array &$errors = []): array
    {
        $formErrors = $form->getErrors();
        if (!empty((string)$formErrors)) {
            $messages = [];
            foreach ($formErrors as $formError) {
                $messages[] = $formError->getMessage();
            }
            $errors[$form->getName()] = implode(', ', $messages);
        }

        foreach ($form->all() as $subForm) {
            self::getErrors($subForm, $errors);
        }

        return $errors;
    }

    /**
     * @param FormInterface $form
     * @param Request $request
     * @param EntityManagerInterface $em
     * @return JSendResponse
     * @throws \Throwable
     */
    public static function handleFormSubmit(
        FormInterface $form,
        Request $request,
        EntityManagerInterface $em
    ): JSendResponse
    {
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return JSendResponse::createFail('Andmete salvestamine ebaõnnestus', 400);
        }

        if ($form->isValid()) {
            $data = $form->getData();
            $name = $form->getName();
            $em->persist($data);
            $em->flush();
            $code = $request->getMethod() === 'POST' ? 201 : 200;
            return JSendResponse::createSuccess([$name => $data], [], $code);
        }

        $errors = self::getErrors($form);

        return JSendResponse::createFail(reset($errors), 400, $errors);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Throwable
     */
    public static function submitForm(
        ContainerInterface $container,
        Request $request,
        EntityManagerInterface $em,
        string $type,
        object $data,
    ): JSendResponse
    {
        $form = $container->get('form.factory')->create($type, $data, [
            'method' => $request->getMethod(),
        ]);

        return self::handleFormSubmit($form, $request, $em);
    }

}
