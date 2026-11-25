<?php

namespace App\Enums;

enum AcademicTitle: string
{
    case ENGINEER = 'Ing.';
    case LICENTIATE = 'Lic.';
    case MASTER = 'M.Sc.';
    case DOCTORATE = 'Ph.D.';
    case UNIVERSITY_STUDENT = 'Univ.';
}
