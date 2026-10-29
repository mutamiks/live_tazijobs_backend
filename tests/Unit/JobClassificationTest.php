<?php

namespace Tests\Unit;

use App\Models\Job;
use PHPUnit\Framework\TestCase;

class JobClassificationTest extends TestCase
{
    public function test_salaries_above_the_threshold_are_skilled(): void
    {
        $this->assertSame('skilled', Job::classificationForSalary(350000, 350001));
    }

    public function test_salaries_at_or_below_the_threshold_are_unskilled(): void
    {
        $this->assertSame('unskilled', Job::classificationForSalary(350000, 350000));
        $this->assertSame('unskilled', Job::classificationForSalary(250000, 350000));
    }

    public function test_minimum_salary_is_used_when_maximum_is_missing(): void
    {
        $this->assertSame('skilled', Job::classificationForSalary(350001, null));
        $this->assertSame('unskilled', Job::classificationForSalary(350000, null));
    }

    public function test_jobs_without_salary_are_unskilled(): void
    {
        $this->assertSame('unskilled', Job::classificationForSalary(null, null));
    }
}
