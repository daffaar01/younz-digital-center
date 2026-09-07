<?php

namespace App\Contracts;

use App\Data\FirebaseUserIdentity;

interface FirebaseTokenVerifier
{
    public function verify(string $idToken): FirebaseUserIdentity;
}
