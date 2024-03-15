<?php

namespace App\Abstractions\AbstractClasses;

use App\Abstractions\Interfaces\UserModelInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class UserModelAbstract extends Authenticatable implements UserModelInterface
{
    protected Controller $controller;

    public function __construct()
    {
        parent::__construct();
        $this->controller = new Controller();
    }

    /**
     * @throws \Exception
     */
    public function getActiveUser(): ?User
    {
        /** @var User $user */
        $user = User::repo()->findById($this->controller->getUserId());
        if(!$user) throw new \Exception('this user does not exist.');
        return $user;
    }



}
