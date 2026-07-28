<?php

namespace App\Enums;

enum RoleType: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case AdministrativeAdmin = 'ADMINISTRATIVE_ADMIN';
    case AcademicAdmin = 'ACADEMIC_ADMIN';
    case FinancialAdmin = 'FINANCIAL_ADMIN';
    case Instructor = 'INSTRUCTOR';
    case Ta = 'TA';
    case Student = 'STUDENT';
}
