<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case JobSeeker = 'job_seeker';
    case Employer = 'employer';
}
