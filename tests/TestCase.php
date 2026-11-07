<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);
        $this->withSession(['otp_verified_user_id' => $user->getAuthIdentifier()]);

        return $this;
    }
}
