<?php

namespace App\Abstractions\Interfaces;

use App\Models\User;

interface UserModelInterface
{
   public function getActiveUser(): ?User;
}
