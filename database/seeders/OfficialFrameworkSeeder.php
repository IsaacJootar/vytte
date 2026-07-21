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

        foreach (self::frameworks() as $spec) {
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
