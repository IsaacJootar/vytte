<?php

namespace Database\Seeders;

use App\Services\OfficialFrameworkComposer;
use Illuminate\Database\Seeder;

/**
 * The official Vytte framework catalogue.
 *
 * Each framework is composed from the published question library rather than carrying its
 * own questions, and published through the governed lifecycle. Adding a framework here is
 * declaring which library questions it groups and how those groups map to measurement
 * domains; the composer does the wiring and the publication.
 *
 * Sections are organised by measurement domain, so a governance section in the hospital
 * framework and a governance section in a programme framework roll up to the same domain
 * and become comparable across subjects.
 */
class OfficialFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $composer = app(OfficialFrameworkComposer::class);

        $published = 0;
        $skipped = 0;
        $allMissing = [];

        foreach (array_merge(self::frameworks(), self::departmentFrameworks()) as $spec) {
            $result = $composer->compose($spec);

            if ($result['status'] === 'published') {
                $published++;
                $this->command?->info("  {$spec['name']}: {$result['placed']} questions placed.");
            } else {
                $skipped++;
                $this->command?->warn("  {$spec['name']}: {$result['status']}.");
            }

            $allMissing = array_merge($allMissing, $result['missing']);
        }

        if ($allMissing !== []) {
            $this->command?->warn('Question codes referenced but not found: '.implode(', ', array_unique($allMissing)));
        }

        $this->command?->info("Official frameworks: {$published} published, {$skipped} skipped.");
    }

    /**
     * The measurement-domain sections every facility-wide framework shares.
     *
     * Defined once so the flagship frameworks stay consistent and a change to the
     * cross-cutting spine reaches all of them.
     *
     * @return array<int, array{domain: string, name: string, questions: array<int, string>}>
     */
    private static function facilityCore(): array
    {
        return [
            ['domain' => 'GOV', 'name' => 'Leadership & Governance', 'questions' => [
                'GOV.001', 'GOV.002', 'GOV.003', 'GOV.004', 'GOV.005', 'GOV.006', 'GOV.007',
                'GOV.008', 'GOV.009', 'GOV.010', 'GOV.011', 'GOV.014', 'GOV.015', 'GOV.016', 'GOV.017',
            ]],
            ['domain' => 'WORK', 'name' => 'Workforce & Capability', 'questions' => [
                'WRK.001', 'WRK.002', 'WRK.003', 'WRK.004', 'WRK.005', 'WRK.006', 'WRK.007',
                'WRK.008', 'WRK.009', 'WRK.010', 'WRK.011', 'WRK.012', 'WRK.013', 'WRK.014', 'WRK.015',
            ]],
            ['domain' => 'SAFE', 'name' => 'Safety & Quality', 'questions' => [
                'QAS.001', 'QAS.002', 'QAS.003', 'QAS.004', 'QAS.005', 'QAS.006', 'QAS.007', 'QAS.008',
                'QAS.009', 'QAS.010', 'QAS.011', 'QAS.012', 'QAS.013', 'QAS.014', 'QAS.015', 'QAS.016',
            ]],
            ['domain' => 'RES', 'name' => 'Infrastructure, Equipment & Supplies', 'questions' => [
                'INF.001', 'INF.002', 'INF.003', 'INF.004', 'INF.005', 'INF.006', 'INF.007', 'INF.008',
                'INF.009', 'INF.010', 'INF.011', 'INF.012', 'INF.013', 'INF.014', 'INF.015', 'INF.016',
                'INF.017', 'INF.018', 'INF.019', 'INF.020',
            ]],
            ['domain' => 'SAFE', 'name' => 'Infection Prevention & Control', 'questions' => [
                'IPC.001', 'IPC.002', 'IPC.003', 'IPC.004', 'IPC.005', 'IPC.006', 'IPC.007', 'IPC.008',
                'IPC.009', 'IPC.010', 'IPC.011', 'IPC.012', 'IPC.013', 'IPC.014', 'IPC.015', 'IPC.016', 'IPC.017',
            ]],
            ['domain' => 'RES', 'name' => 'Water, Sanitation & Hygiene', 'questions' => [
                'WASH.001', 'WASH.002', 'WASH.003', 'WASH.004', 'WASH.005', 'WASH.006', 'WASH.007', 'WASH.008',
                'WASH.009', 'WASH.010', 'WASH.011', 'WASH.012', 'WASH.013', 'WASH.014', 'WASH.015', 'WASH.016',
            ]],
            ['domain' => 'INFO', 'name' => 'Information & Records', 'questions' => [
                'INFO.001', 'INFO.002', 'INFO.003', 'INFO.004', 'INFO.005', 'INFO.006',
                'INFO.007', 'INFO.008', 'INFO.009', 'INFO.010', 'INFO.011', 'INFO.012', 'INFO.013',
            ]],
            ['domain' => 'FIN', 'name' => 'Financing & Resource Management', 'questions' => [
                'FIN.001', 'FIN.002', 'FIN.003', 'FIN.004', 'FIN.005', 'FIN.006', 'FIN.007', 'FIN.008', 'FIN.009', 'FIN.010',
            ]],
            ['domain' => 'PCOM', 'name' => 'Person-Centredness & Community', 'questions' => [
                'PCOM.001', 'PCOM.002', 'PCOM.003', 'PCOM.004', 'PCOM.005', 'PCOM.006',
                'PCOM.007', 'PCOM.008', 'PCOM.009', 'PCOM.010', 'PCOM.011', 'PCOM.012',
            ]],
        ];
    }

    /**
     * A lighter facility core for smaller facilities, dropping the sections a primary
     * clinic is not expected to carry in depth.
     *
     * @return array<int, array{domain: string, name: string, questions: array<int, string>}>
     */
    private static function primaryCore(): array
    {
        return [
            ['domain' => 'GOV', 'name' => 'Leadership & Governance', 'questions' => [
                'GOV.001', 'GOV.002', 'GOV.006', 'GOV.007', 'GOV.009', 'GOV.010', 'GOV.011', 'GOV.015', 'GOV.016', 'GOV.017',
            ]],
            ['domain' => 'WORK', 'name' => 'Workforce & Capability', 'questions' => [
                'WRK.001', 'WRK.004', 'WRK.005', 'WRK.006', 'WRK.007', 'WRK.008', 'WRK.009', 'WRK.011', 'WRK.014', 'WRK.015',
            ]],
            ['domain' => 'SAFE', 'name' => 'Safety & Quality', 'questions' => [
                'QAS.002', 'QAS.003', 'QAS.004', 'QAS.008', 'QAS.009', 'QAS.011', 'QAS.012', 'QAS.013', 'QAS.014', 'QAS.015', 'QAS.016',
            ]],
            ['domain' => 'RES', 'name' => 'Infrastructure, Equipment & Supplies', 'questions' => [
                'INF.001', 'INF.002', 'INF.003', 'INF.004', 'INF.006', 'INF.009', 'INF.011',
                'INF.013', 'INF.014', 'INF.015', 'INF.016', 'INF.017', 'INF.018', 'INF.019', 'INF.020',
            ]],
            ['domain' => 'SAFE', 'name' => 'Infection Prevention & Control', 'questions' => [
                'IPC.001', 'IPC.002', 'IPC.004', 'IPC.005', 'IPC.006', 'IPC.007', 'IPC.009', 'IPC.010', 'IPC.012', 'IPC.014', 'IPC.017',
            ]],
            ['domain' => 'RES', 'name' => 'Water, Sanitation & Hygiene', 'questions' => [
                'WASH.001', 'WASH.002', 'WASH.004', 'WASH.005', 'WASH.008', 'WASH.009', 'WASH.010', 'WASH.011', 'WASH.013', 'WASH.016',
            ]],
            ['domain' => 'INFO', 'name' => 'Information & Records', 'questions' => [
                'INFO.001', 'INFO.002', 'INFO.003', 'INFO.004', 'INFO.007', 'INFO.009', 'INFO.013',
            ]],
            ['domain' => 'PCOM', 'name' => 'Person-Centredness & Community', 'questions' => [
                'PCOM.001', 'PCOM.002', 'PCOM.003', 'PCOM.004', 'PCOM.005', 'PCOM.006',
                'PCOM.007', 'PCOM.008', 'PCOM.009', 'PCOM.010', 'PCOM.011', 'PCOM.012',
            ]],
        ];
    }

    /**
     * The supporting systems a focused programme framework should still check around its
     * service: a light read on governance, supplies, information and data burden. Kept
     * short so the programme service remains the focus.
     *
     * @return array<int, array{domain: string, name: string, questions: array<int, string>}>
     */
    private static function programmeSupport(): array
    {
        return [
            ['domain' => 'GOV', 'name' => 'Programme Governance', 'questions' => [
                'GOV.006', 'GOV.008', 'WRK.007', 'WRK.009',
            ]],
            ['domain' => 'RES', 'name' => 'Commodities & Cold Chain', 'questions' => [
                'INF.013', 'INF.014', 'INF.015', 'INF.016', 'INF.017', 'INF.018',
            ]],
            ['domain' => 'INFO', 'name' => 'Records & Reporting', 'questions' => [
                'INFO.001', 'INFO.003', 'INFO.004', 'INFO.005', 'BURD.001', 'BURD.003',
            ]],
        ];
    }

    /**
     * Reusable department-only frameworks used by comprehensive composition.
     *
     * Focused programme frameworks intentionally include shared governance, commodity,
     * and reporting questions. Reusing those frameworks in one comprehensive assessment
     * would duplicate question identities. These department frameworks place only the
     * questions owned by that department, so they can be composed safely.
     *
     * @return array<int, array{module: string, code: string, name: string, description: string, type: string, sections: array<int, array{domain: string, name: string, questions: array<int, string>}>}>
     */
    private static function departmentFrameworks(): array
    {
        return [
            self::departmentFramework('GOV', 'GOVERNANCE_DEPARTMENT', 'Leadership & Governance Department Framework', 'GOV', 'Leadership & Governance', 'GOV', 17),
            [
                'module' => 'INF',
                'code' => 'INFRASTRUCTURE_IPC_DEPARTMENT',
                'name' => 'Infrastructure, Utilities & Infection Prevention Department Framework',
                'description' => 'Reusable department framework for infrastructure, utilities, equipment, supplies, and infection prevention.',
                'type' => 'DEPARTMENT',
                'sections' => [
                    ['domain' => 'RES', 'name' => 'Infrastructure, Equipment & Supplies', 'questions' => self::questionCodes('INF', 20)],
                    ['domain' => 'SAFE', 'name' => 'Infection Prevention & Control', 'questions' => self::questionCodes('IPC', 17)],
                ],
            ],
            self::departmentFramework('WSHF', 'WASH_DEPARTMENT', 'Water, Sanitation & Hygiene Department Framework', 'RES', 'Water, Sanitation & Hygiene', 'WASH', 16),
            self::departmentFramework('HRM', 'WORKFORCE_DEPARTMENT', 'Human Resource Management Department Framework', 'WORK', 'Workforce & Capability', 'WRK', 15),
            [
                'module' => 'REC',
                'code' => 'RECORDS_DEPARTMENT',
                'name' => 'Records & Health Information Management Department Framework',
                'description' => 'Reusable department framework for records, reporting, information use, and data burden.',
                'type' => 'DEPARTMENT',
                'sections' => [
                    ['domain' => 'INFO', 'name' => 'Information & Records', 'questions' => self::questionCodes('INFO', 13)],
                    ['domain' => 'INFO', 'name' => 'Reporting Burden', 'questions' => self::questionCodes('BURD', 6)],
                ],
            ],
            self::departmentFramework('QAS', 'QUALITY_SAFETY_DEPARTMENT', 'Quality & Patient Safety Department Framework', 'SAFE', 'Quality & Patient Safety', 'QAS', 16),
            self::departmentFramework('ANC', 'ANTENATAL_DEPARTMENT', 'Antenatal Care Department Framework', 'SERV', 'Antenatal Care', 'MAT', 11),
            [
                'module' => 'IMM',
                'code' => 'CHILD_IMMUNIZATION_DEPARTMENT',
                'name' => 'Child Health & Immunization Department Framework',
                'description' => 'Reusable department framework for child health and routine immunization services.',
                'type' => 'DEPARTMENT',
                'sections' => [
                    ['domain' => 'SERV', 'name' => 'Child Health Services', 'questions' => self::questionCodes('CHD', 8)],
                    ['domain' => 'SERV', 'name' => 'Immunization Services', 'questions' => self::questionCodes('IMM', 10)],
                ],
            ],
            self::departmentFramework('NUT', 'NUTRITION_DEPARTMENT', 'Nutrition Services Department Framework', 'SERV', 'Nutrition Services', 'NUT', 7),
            self::departmentFramework('PHM', 'PHARMACY_DEPARTMENT', 'Pharmacy Department Framework', 'RES', 'Pharmacy & Medicines', 'PHA', 8),
            self::departmentFramework('COM', 'COMMUNITY_DEPARTMENT', 'Community Health & Outreach Department Framework', 'PCOM', 'Person-Centredness & Community', 'PCOM', 12),
            self::departmentFramework('LAB', 'LABORATORY_DEPARTMENT', 'Laboratory Department Framework', 'SERV', 'Laboratory Services', 'LAB', 8),
            [
                'module' => 'HTB',
                'code' => 'HIV_TB_PMTCT_DEPARTMENT',
                'name' => 'HIV, TB & PMTCT Services Department Framework',
                'description' => 'Reusable department framework for HIV, TB, and PMTCT services.',
                'type' => 'DEPARTMENT',
                'sections' => [
                    ['domain' => 'SERV', 'name' => 'HIV & PMTCT Services', 'questions' => self::questionCodes('HIV', 11)],
                    ['domain' => 'SERV', 'name' => 'TB Services', 'questions' => self::questionCodes('TB', 9)],
                ],
            ],
            self::departmentFramework('MAL', 'MALARIA_DEPARTMENT', 'Malaria Services Department Framework', 'SERV', 'Malaria Services', 'MAL', 9),
            self::departmentFramework('MNH', 'MENTAL_HEALTH_DEPARTMENT', 'Mental Health Services Department Framework', 'SERV', 'Mental Health Services', 'MEN', 7),
            self::departmentFramework('OPD', 'OUTPATIENT_DEPARTMENT', 'Outpatient Department Framework', 'SERV', 'Outpatient Services', 'OPD', 6),
            self::departmentFramework('REF', 'REFERRAL_DEPARTMENT', 'Referral Management Department Framework', 'SERV', 'Referral Management', 'REF', 6),
            self::departmentFramework('IPD', 'INPATIENT_DEPARTMENT', 'In-Patient Ward Department Framework', 'SERV', 'In-Patient Services', 'IPD', 6),
            self::departmentFramework('EMR', 'EMERGENCY_DEPARTMENT', 'Emergency & Accident Unit Department Framework', 'SERV', 'Emergency & Accident Services', 'EMR', 8),
            self::departmentFramework('THR', 'THEATRE_DEPARTMENT', 'Theatre & Surgical Services Department Framework', 'SAFE', 'Theatre & Surgical Services', 'THR', 6),
            self::departmentFramework('ICU', 'CRITICAL_CARE_DEPARTMENT', 'Intensive & High Dependency Care Department Framework', 'SERV', 'Intensive & High Dependency Care', 'ICU', 6),
            self::departmentFramework('RAD', 'RADIOLOGY_DEPARTMENT', 'Radiology & Imaging Department Framework', 'SERV', 'Radiology & Imaging', 'RAD', 6),
            self::departmentFramework('BLB', 'BLOOD_BANK_DEPARTMENT', 'Blood Bank & Transfusion Services Department Framework', 'SAFE', 'Blood Bank & Transfusion Services', 'BLB', 6),
            self::departmentFramework('LBD', 'LABOUR_DELIVERY_DEPARTMENT', 'Labour & Delivery Department Framework', 'SERV', 'Labour & Delivery', 'LBD', 6),
            self::departmentFramework('PNC', 'POSTNATAL_DEPARTMENT', 'Postnatal Care Department Framework', 'SERV', 'Postnatal Care', 'PNC', 6),
            self::departmentFramework('FP', 'FAMILY_PLANNING_DEPARTMENT', 'Family Planning Department Framework', 'SERV', 'Family Planning', 'FP', 6),
            self::departmentFramework('SRH', 'SEXUAL_REPRODUCTIVE_DEPARTMENT', 'Sexual & Reproductive Health Department Framework', 'SERV', 'Sexual & Reproductive Health', 'SRH', 6),
            self::departmentFramework('NCD', 'NCD_DEPARTMENT', 'Non-Communicable Disease Services Department Framework', 'SERV', 'Non-Communicable Disease Services', 'NCD', 6),
            self::departmentFramework('ADO', 'ADOLESCENT_DEPARTMENT', 'Adolescent & Youth Health Department Framework', 'PCOM', 'Adolescent & Youth Health', 'ADO', 6),
            self::departmentFramework('OLD', 'GERIATRIC_DEPARTMENT', 'Older People & Geriatric Care Department Framework', 'PCOM', 'Older People & Geriatric Care', 'OLD', 6),
            self::departmentFramework('REH', 'REHABILITATION_DEPARTMENT', 'Rehabilitation Services Department Framework', 'SERV', 'Rehabilitation Services', 'REH', 6),
            self::departmentFramework('PAL', 'PALLIATIVE_DEPARTMENT', 'Palliative & End-of-Life Care Department Framework', 'PCOM', 'Palliative & End-of-Life Care', 'PAL', 6),
            self::departmentFramework('ORL', 'ORAL_HEALTH_DEPARTMENT', 'Oral Health & Dental Services Department Framework', 'SERV', 'Oral Health & Dental Services', 'ORL', 6),
            self::departmentFramework('EYE', 'EYE_HEALTH_DEPARTMENT', 'Eye Health Services Department Framework', 'SERV', 'Eye Health Services', 'EYE', 6),
            self::departmentFramework('AMR', 'ANTIMICROBIAL_DEPARTMENT', 'Antimicrobial Stewardship Department Framework', 'SAFE', 'Antimicrobial Stewardship', 'AMR', 6),
            self::departmentFramework('OBR', 'OUTBREAK_DEPARTMENT', 'Outbreak Preparedness & Response Department Framework', 'SAFE', 'Outbreak Preparedness & Response', 'OBR', 6),
            self::departmentFramework('NTD', 'NTD_DEPARTMENT', 'Neglected Tropical Disease Services Department Framework', 'SERV', 'Neglected Tropical Disease Services', 'NTD', 6),
            self::departmentFramework('DIS', 'DISABILITY_DEPARTMENT', 'Disability & Inclusion Department Framework', 'PCOM', 'Disability & Inclusion', 'DIS', 6),
            self::departmentFramework('HPR', 'HEALTH_PROMOTION_DEPARTMENT', 'Health Promotion & Education Department Framework', 'PCOM', 'Health Promotion & Education', 'HPR', 6),
            self::departmentFramework('ENV', 'CLIMATE_RESILIENCE_DEPARTMENT', 'Environment & Climate Resilience Department Framework', 'RES', 'Environment & Climate Resilience', 'ENV', 6),
            self::departmentFramework('OCC', 'OCCUPATIONAL_HEALTH_DEPARTMENT', 'Staff Health & Safety Department Framework', 'WORK', 'Staff Health & Safety', 'OCC', 6),
            self::departmentFramework('FIN', 'FINANCE_DEPARTMENT', 'Finance, Billing & Insurance Claims Department Framework', 'FIN', 'Finance, Billing & Insurance Claims', 'FIN', 10),
        ];
    }

    /**
     * @return array{module: string, code: string, name: string, description: string, type: string, sections: array<int, array{domain: string, name: string, questions: array<int, string>}>}
     */
    private static function departmentFramework(
        string $module,
        string $code,
        string $name,
        string $domain,
        string $sectionName,
        string $questionPrefix,
        int $questionCount,
    ): array {
        return [
            'module' => $module,
            'code' => $code,
            'name' => $name,
            'description' => "Reusable department framework for {$sectionName}.",
            'type' => 'DEPARTMENT',
            'sections' => [
                ['domain' => $domain, 'name' => $sectionName, 'questions' => self::questionCodes($questionPrefix, $questionCount)],
            ],
        ];
    }

    /** @return list<string> */
    private static function questionCodes(string $prefix, int $count): array
    {
        return array_map(
            fn (int $number): string => sprintf('%s.%03d', $prefix, $number),
            range(1, $count),
        );
    }

    /**
     * @return array<int, array{module: string, code: string, name: string, description: string, type: string, sections: array<int, array{domain: string, name: string, questions: array<int, string>}>}>
     */
    private static function frameworks(): array
    {
        return [
            [
                'module' => 'FAC',
                'code' => 'HOSPITAL_READINESS',
                'name' => 'Hospital Operational Readiness',
                'description' => 'Whole-hospital readiness across leadership, workforce, safety, infrastructure, infection control, WASH, information, financing and patient experience.',
                'type' => 'DEPARTMENT',
                'sections' => self::facilityCore(),
            ],
            [
                'module' => 'FAC',
                'code' => 'PHC_ASSESSMENT',
                'name' => 'Primary Healthcare Facility Assessment',
                'description' => 'General assessment of a primary healthcare facility across the services and systems it is expected to run.',
                'type' => 'DEPARTMENT',
                'sections' => self::primaryCore(),
            ],
            [
                'module' => 'INF',
                'code' => 'IPC_FRAMEWORK',
                'name' => 'Infection Prevention & Control Assessment',
                'description' => 'Focused assessment of IPC against the WHO minimum requirements: programme, guidelines, training, hand hygiene, PPE, reprocessing, isolation, surveillance and audit.',
                'type' => 'FOCUSED',
                'sections' => [
                    ['domain' => 'SAFE', 'name' => 'Infection Prevention & Control', 'questions' => [
                        'IPC.001', 'IPC.002', 'IPC.003', 'IPC.004', 'IPC.005', 'IPC.006', 'IPC.007', 'IPC.008',
                        'IPC.009', 'IPC.010', 'IPC.011', 'IPC.012', 'IPC.013', 'IPC.014', 'IPC.015', 'IPC.016', 'IPC.017',
                    ]],
                    ['domain' => 'RES', 'name' => 'Supporting Infrastructure', 'questions' => [
                        'INF.003', 'INF.004', 'INF.007', 'INF.012', 'WASH.001', 'WASH.008', 'WASH.009', 'WASH.011',
                    ]],
                ],
            ],
            [
                'module' => 'WSHF',
                'code' => 'WASH_FRAMEWORK',
                'name' => 'WASH in Health Care Facilities',
                'description' => 'Focused assessment of water, sanitation, hygiene, health care waste and environmental cleaning, following WHO/UNICEF WASH FIT.',
                'type' => 'FOCUSED',
                'sections' => [
                    ['domain' => 'RES', 'name' => 'Water, Sanitation & Hygiene', 'questions' => [
                        'WASH.001', 'WASH.002', 'WASH.003', 'WASH.004', 'WASH.005', 'WASH.006', 'WASH.007', 'WASH.008',
                        'WASH.009', 'WASH.010', 'WASH.011', 'WASH.012', 'WASH.013', 'WASH.014', 'WASH.015', 'WASH.016',
                    ]],
                    ['domain' => 'SAFE', 'name' => 'Linked Infection Control', 'questions' => [
                        'IPC.004', 'IPC.005', 'IPC.009', 'IPC.012', 'IPC.013',
                    ]],
                ],
            ],

            // Stage 4 — focused disease-programme frameworks. Each leads with its service
            // and adds a light read on the systems around it, drawn from the shared library.
            [
                'module' => 'HTB',
                'code' => 'HIV_PROGRAMME',
                'name' => 'HIV Programme Assessment',
                'description' => 'Focused assessment of HIV testing, treatment, PMTCT, prevention and retention, with the commodities, records and confidentiality the programme depends on.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'HIV & PMTCT Services', 'questions' => [
                        'HIV.001', 'HIV.002', 'HIV.003', 'HIV.004', 'HIV.005', 'HIV.006',
                        'HIV.007', 'HIV.008', 'HIV.009', 'HIV.010', 'HIV.011',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'HTB',
                'code' => 'TB_PROGRAMME',
                'name' => 'TB Programme Assessment',
                'description' => 'Focused assessment of TB screening, diagnosis, treatment, adherence and infection control, with commodities and reporting.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'TB Services', 'questions' => [
                        'TB.001', 'TB.002', 'TB.003', 'TB.004', 'TB.005', 'TB.006', 'TB.007', 'TB.008', 'TB.009',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'MAL',
                'code' => 'MALARIA_PROGRAMME',
                'name' => 'Malaria Programme Assessment',
                'description' => 'Focused assessment of malaria diagnosis, case management, severe malaria, prevention and surveillance, with commodities and reporting.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Malaria Services', 'questions' => [
                        'MAL.001', 'MAL.002', 'MAL.003', 'MAL.004', 'MAL.005', 'MAL.006', 'MAL.007', 'MAL.008', 'MAL.009',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'IMM',
                'code' => 'IMMUNIZATION_PROGRAMME',
                'name' => 'Immunization Programme Assessment',
                'description' => 'Focused assessment of routine immunization, cold chain, defaulter follow-up, safety and outreach, with commodities and reporting.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Immunization Services', 'questions' => [
                        'IMM.001', 'IMM.002', 'IMM.003', 'IMM.004', 'IMM.005', 'IMM.006',
                        'IMM.007', 'IMM.008', 'IMM.009', 'IMM.010',
                    ]],
                ], self::programmeSupport()),
            ],

            // Stage 5 — maternal, child, nutrition and mental health frameworks.
            [
                'module' => 'ANC',
                'code' => 'MATERNAL_NEWBORN',
                'name' => 'Maternal & Newborn Care Assessment',
                'description' => 'Focused assessment of antenatal care, high-risk identification, delivery, emergency obstetric and newborn care, and postnatal follow-up.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Maternal & Newborn Services', 'questions' => [
                        'MAT.001', 'MAT.002', 'MAT.003', 'MAT.004', 'MAT.005', 'MAT.006',
                        'MAT.007', 'MAT.008', 'MAT.009', 'MAT.010', 'MAT.011',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'IMM',
                'code' => 'CHILD_HEALTH',
                'name' => 'Child Health Assessment',
                'description' => 'Focused assessment of integrated child illness care, growth monitoring, immunization checks and newborn care.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Child Health Services', 'questions' => [
                        'CHD.001', 'CHD.002', 'CHD.003', 'CHD.004', 'CHD.005', 'CHD.006', 'CHD.007', 'CHD.008',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'NUT',
                'code' => 'NUTRITION_PROGRAMME',
                'name' => 'Nutrition Programme Assessment',
                'description' => 'Focused assessment of malnutrition screening and treatment, feeding counselling, micronutrients and follow-up.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Nutrition Services', 'questions' => [
                        'NUT.001', 'NUT.002', 'NUT.003', 'NUT.004', 'NUT.005', 'NUT.006', 'NUT.007',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'MNH',
                'code' => 'MENTAL_HEALTH_SERVICES',
                'name' => 'Mental Health Services Assessment',
                'description' => 'Focused assessment of mental health screening, treatment or referral, psychotropic availability, follow-up, confidentiality and dignity.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Mental Health Services', 'questions' => [
                        'MEN.001', 'MEN.002', 'MEN.003', 'MEN.004', 'MEN.005', 'MEN.006', 'MEN.007',
                    ]],
                ], self::programmeSupport()),
            ],

            // Stage 6 — clinical service frameworks.
            [
                'module' => 'LAB',
                'code' => 'LABORATORY_ASSESSMENT',
                'name' => 'Laboratory Services Assessment',
                'description' => 'Focused assessment of laboratory test menu, reagents, quality control, biosafety, equipment, turnaround and referral.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Laboratory Services', 'questions' => [
                        'LAB.001', 'LAB.002', 'LAB.003', 'LAB.004', 'LAB.005', 'LAB.006', 'LAB.007', 'LAB.008',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'PHM',
                'code' => 'PHARMACY_ASSESSMENT',
                'name' => 'Pharmacy & Medicines Assessment',
                'description' => 'Focused assessment of medicine availability, storage, expiry control, stock management, dispensing, rational use and controlled substances.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Pharmacy Services', 'questions' => [
                        'PHA.001', 'PHA.002', 'PHA.003', 'PHA.004', 'PHA.005', 'PHA.006', 'PHA.007', 'PHA.008',
                    ]],
                ], self::programmeSupport()),
            ],
            [
                'module' => 'EMR',
                'code' => 'EMERGENCY_CARE',
                'name' => 'Emergency Care Assessment',
                'description' => 'Focused assessment of triage, resuscitation, emergency commodities, oxygen, trained staff, referral and transport.',
                'type' => 'FOCUSED',
                'sections' => array_merge([
                    ['domain' => 'SERV', 'name' => 'Emergency Services', 'questions' => [
                        'EMR.001', 'EMR.002', 'EMR.003', 'EMR.004', 'EMR.005', 'EMR.006', 'EMR.007', 'EMR.008',
                    ]],
                ], self::programmeSupport()),
            ],
        ];
    }
}
