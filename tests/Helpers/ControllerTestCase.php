<?php

namespace Tests\Helpers;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class ControllerTestCase extends WebTestCase
{
    protected static ?KernelBrowser $client;

    protected function setUp(): void
    {
        self::$client = static::createClient();
        // Hautelook's RefreshDatabaseTrait begins a transaction on kernel boot and rolls it back
        // on kernel shutdown. KernelBrowser reboots the kernel between requests by default, so
        // any writes a first request makes get rolled back before the second request sees them.
        // Disable that reboot so tests can POST-then-GET within the same transaction.
        self::$client->disableReboot();
        self::$client->followRedirects();
    }

    /**
     * @param string $username
     * @return User
     */
    protected function login(string $username): User
    {
        $client = static::$client;

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneBy(['username' => $username]);
        $this->assertNotNull($testUser);
        // simulate $testUser being logged in
        $client->loginUser($testUser);

        return $testUser;
    }

    protected function loginSuperAdmin(): User
    {
        return $this->login('user1');
    }

    protected function loginAdmin(): User
    {
        return $this->login('user2');
    }

    protected function loginSimpleUser(): User
    {
        return $this->login('user3');
    }

    /**
     * @throws JsonException
     * @return array
     */
    protected function getResponseBodyJson(): array
    {
        $response = self::$client->getResponse();
        try {
            return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JsonException('Failed to decode response content', 0, $e);
        }
    }

    /**
     * Checks that response data is correct at given array key.
     *
     * @param array|string|number|boolean|null $expected
     * @param string $path  dot-separated array keys for nesting
     */
    protected function assertResponseEqualsJsonPath(mixed $expected, string $path = ''): void
    {
        $json = $this->getResponseBodyJson();

        if (empty($path)) {
            $this->assertEquals($expected, $json);
            return;
        }

        $currentElement = $json;
        $breadcrumbs = [];
        $parts = explode('.', $path);

        foreach ($parts as $part) {
            $breadcrumbs[] = $part;
            $this->assertArrayHasKey($part, $currentElement, 'Response does not contain data at path ' . implode('.', $breadcrumbs) . '. Got: ' . json_encode($json));
            $currentElement = $currentElement[$part];
        }

        $this->assertEquals($expected, $currentElement, 'Failed asserting data at path ' . implode('.', $breadcrumbs));
    }

    /**
     * Assert array has expected number of items at given JSON path
     * @param int $expected
     * @param string $path
     * @return void
     */
    protected function assertResponseCountJsonPath(int $expected, string $path = ''): void
    {
        $json = $this->getResponseBodyJson();

        if (empty($path)) {
            $this->assertCount($expected, $json);
            return;
        }

        $currentElement = $json;
        $breadcrumbs = [];
        $parts = explode('.', $path);

        foreach ($parts as $part) {
            $breadcrumbs[] = $part;
            $this->assertArrayHasKey($part, $currentElement, 'Response does not contain data at path ' . implode('.', $breadcrumbs) . '. Got: ' . json_encode($json));
            $currentElement = $currentElement[$part];
        }

        $this->assertCount($expected, $currentElement, 'Failed asserting count at path ' . implode('.', $breadcrumbs));
    }
}
