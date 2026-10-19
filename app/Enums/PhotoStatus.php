<?php

namespace App\Enums;

enum PhotoStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
