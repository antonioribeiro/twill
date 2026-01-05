<?php

namespace A17\Twill\Tests\Integration;

use A17\Twill\Models\User;
use A17\Twill\Notifications\Reset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Tests for case-insensitive email authentication.
 *
 * @see https://github.com/area17/twill/issues/2789
 */
class CaseInsensitiveEmailTest extends TestCase
{
    public function testUserCanLoginWithLowercaseEmailWhenRegisteredWithMixedCase(): void
    {
        // Create user with mixed case email
        $mixedCaseEmail = 'Test.User@Example.COM';
        $password = 'secret';

        $user = User::make([
            'name' => 'Test User',
            'email' => $mixedCaseEmail,
            'role' => 'ADMIN',
            'published' => true,
        ]);
        $user->password = Hash::make($password);
        $user->save();

        // Verify not authenticated before login
        $this->assertGuest('twill_users');

        // Try to login with lowercase email
        $this->loginAs(strtolower($mixedCaseEmail), $password);

        $this->assertAuthenticated('twill_users');
    }

    public function testUserCanLoginWithUppercaseEmailWhenRegisteredWithLowercase(): void
    {
        // Create user with lowercase email
        $lowercaseEmail = 'lowercase.user@example.com';
        $password = 'secret';

        $user = User::make([
            'name' => 'Lowercase User',
            'email' => $lowercaseEmail,
            'role' => 'ADMIN',
            'published' => true,
        ]);
        $user->password = Hash::make($password);
        $user->save();

        // Verify not authenticated before login
        $this->assertGuest('twill_users');

        // Try to login with uppercase email
        $this->loginAs(strtoupper($lowercaseEmail), $password);

        $this->assertAuthenticated('twill_users');
    }

    public function testUserCanLoginWithRandomCaseEmailVariation(): void
    {
        // Create user with standard email
        $originalEmail = 'random.case@example.com';
        $password = 'secret';

        $user = User::make([
            'name' => 'Random Case User',
            'email' => $originalEmail,
            'role' => 'ADMIN',
            'published' => true,
        ]);
        $user->password = Hash::make($password);
        $user->save();

        // Verify not authenticated before login
        $this->assertGuest('twill_users');

        // Try to login with alternating case email
        $randomCaseEmail = 'RaNdOm.CaSe@ExAmPlE.cOm';
        $this->loginAs($randomCaseEmail, $password);

        $this->assertAuthenticated('twill_users');
    }

    public function testPasswordResetEmailSentWithDifferentEmailCase(): void
    {
        Notification::fake();

        // Create user with mixed case email
        $mixedCaseEmail = 'Reset.User@Example.COM';

        $user = User::make([
            'name' => 'Reset User',
            'email' => $mixedCaseEmail,
            'role' => 'ADMIN',
            'published' => true,
        ]);
        $user->password = Hash::make('secret');
        $user->save();

        // Request password reset with lowercase email
        $this->httpRequestAssert('/twill/password/email', 'POST', [
            '_token' => csrf_token(),
            'email' => strtolower($mixedCaseEmail),
        ], 404); // Same pattern as PasswordsTest

        // Assert notification was sent to the user
        Notification::assertSentTo($user, Reset::class);
    }

    public function testSuperAdminCanLoginWithDifferentEmailCase(): void
    {
        // The superAdmin is created with a faker-generated email
        // Try to login with uppercase version of that email
        $uppercaseEmail = strtoupper($this->superAdmin()->email);

        // Verify not authenticated before login
        $this->assertGuest('twill_users');

        $this->loginAs($uppercaseEmail, $this->superAdmin()->unencrypted_password);

        $this->assertAuthenticated('twill_users');
    }

    public function testAutologinWorks(): void
    {
        $email = 'autologin@example.com';
        $password = 'autologin-secret';

        // Create user for autologin
        $user = User::make([
            'name' => 'Autologin User',
            'email' => $email,
            'role' => 'ADMIN',
            'published' => true,
        ]);
        $user->password = Hash::make($password);
        $user->save();

        // Configure autologin
        config([
            'twill.autologin.enabled' => true,
            'twill.autologin.email' => $email,
            'twill.autologin.password' => $password,
            'twill.autologin.environments' => [app()->environment()],
        ]);

        // Verify not authenticated before autologin
        $this->assertGuest('twill_users');

        // Visit login page - should trigger autologin
        $this->get('/twill/login');

        $this->assertAuthenticated('twill_users');
    }

    public function testAutologinWorksWithDifferentEmailCase(): void
    {
        // User registered with mixed case email
        $registeredEmail = 'AutoLogin.User@Example.COM';
        $password = 'autologin-secret';

        $user = User::make([
            'name' => 'Autologin Case User',
            'email' => $registeredEmail,
            'role' => 'ADMIN',
            'published' => true,
        ]);
        $user->password = Hash::make($password);
        $user->save();

        // Configure autologin with lowercase email (different case than registered)
        config([
            'twill.autologin.enabled' => true,
            'twill.autologin.email' => strtolower($registeredEmail),
            'twill.autologin.password' => $password,
            'twill.autologin.environments' => [app()->environment()],
        ]);

        // Verify not authenticated before autologin
        $this->assertGuest('twill_users');

        // Visit login page - should trigger autologin with case-insensitive email lookup
        $this->get('/twill/login');

        $this->assertAuthenticated('twill_users');
    }
}
