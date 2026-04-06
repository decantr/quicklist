<?php

namespace App\Enums;

enum SizeType: string
{
	case Grams = 'g';
	case Millilitres = 'ml';
	case Count = 'count';
	case Pint = 'pt';
}
