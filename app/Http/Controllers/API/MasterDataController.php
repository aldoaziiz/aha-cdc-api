<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Clinic;
use App\Models\GuardianRole;
use App\Models\Payer;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolEducation;
use App\Models\StaffRole;

class MasterDataController extends Controller
{
    public function index()
    {
        return response()->json([

            'cities' => City::orderBy('name', 'asc')
                ->get(),

            'clinics' => Clinic::orderBy('name')
                ->get(),

            'guardian_roles' => GuardianRole::orderBy('name')
                ->get(),

            'payers' => Payer::orderBy('name')
                ->get(),

            'programs' => Program::orderBy('name', 'asc')
                ->get(),

            'schools' => School::orderBy('name', 'asc')
                ->get(),

            'school_classes' => SchoolClass::orderBy('name', 'asc')
                ->get(),

            'school_educations' => SchoolEducation::orderBy('name')
                ->get(),

            'staff_roles' => StaffRole::orderBy('name', 'asc')
                ->get(),

        ]);
    }
}
