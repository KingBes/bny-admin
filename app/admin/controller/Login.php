<?php

namespace app\admin\controller;

use app\BaseController;

class Login extends BaseController
{
    public function index(): string
    {
        return $this->fetch();
    }
}
