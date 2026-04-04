<?php

namespace App\Enums;

enum SizeType: string
{
    case Weight = 'weight';
    case Volume = 'volume';
    case Quantity = 'quantity';
}
