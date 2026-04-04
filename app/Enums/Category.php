<?php

namespace App\Enums;

enum Category: string
{
	case Bakery = 'bakery';
	case Cupboard = 'cupboard';
	case Dairy = 'dairy';
	case Frozen = 'frozen';
	case Meat = 'meat';
	case Produce = 'produce';
	case Other = 'other';
}
