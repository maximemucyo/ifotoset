<?php

namespace App\Enums;

enum Visibility: string
{
    case Private = 'private';
    case Password = 'password';
    case Public = 'public';
}
