# Department Content Review V1

Date: 2026-07-31  
Status: **Human methodology review required before publication**

## Purpose

This review closes every department currently shown as `coming soon` in comprehensive
facility assessments. It covers all supported health-facility profiles, not only the
General Hospital project where the gap was reported.

The exact proposed wording is stored in
`database/content/official_department_questions_v1.php`. That file is intentionally not
connected to `DatabaseSeeder` until a human reviewer approves it. This preserves the
governance rule that AI may prepare official-content drafts but cannot approve or publish
them.

## Scope

| Item | Count |
|---|---:|
| Current published question identities | 238 |
| New source-informed draft questions | 150 |
| Departments receiving new questions | 25 |
| Departments reusing existing governed questions | 2 |
| Missing department frameworks closed | 27 |
| Question identities after approval | 388 |

Every new department receives six concise readiness questions. All are Vytte-authored
wording informed by, but not copied from, the cited source. The proposed initial response
scale is `Yes / Partially / No` scored `100 / 50 / 0`. No new option is marked as an
automatic critical failure in this pass; that decision requires explicit clinical review.

## Department and source matrix

| Department | Planned questions | Measurement domain | Primary source |
|---|---:|---|---|
| Outpatient Department | 6 new | Service Delivery | WHO HHFA |
| Referral Management | 6 new | Service Delivery | WHO Emergency Care Toolkit |
| In-Patient Ward | 6 new | Service Delivery | WHO HHFA |
| Emergency & Accident Unit | 8 existing | Service Delivery | WHO Emergency Care Toolkit |
| Theatre & Surgical Services | 6 new | Safety & Quality | WHO Surgical Safety resources |
| Intensive & High Dependency Care | 6 new | Service Delivery | WHO emergency, critical and operative care resources |
| Radiology & Imaging | 6 new | Service Delivery | IAEA diagnostic-radiology quality assurance |
| Blood Bank & Transfusion Services | 6 new | Safety & Quality | WHO blood safety and availability |
| Labour & Delivery | 6 new | Service Delivery | WHO maternal and newborn quality standards |
| Postnatal Care | 6 new | Service Delivery | WHO positive postnatal experience recommendations |
| Family Planning | 6 new | Service Delivery | WHO family-planning provider handbook |
| Sexual & Reproductive Health | 6 new | Service Delivery | WHO family-planning and SRH guidance |
| Non-Communicable Disease Services | 6 new | Service Delivery | WHO PEN |
| Adolescent & Youth Health | 6 new | Person-Centredness & Community | WHO/UNAIDS adolescent service standards |
| Older People & Geriatric Care | 6 new | Person-Centredness & Community | WHO ICOPE |
| Rehabilitation Services | 6 new | Service Delivery | WHO rehabilitation guide for action |
| Palliative & End-of-Life Care | 6 new | Person-Centredness & Community | WHO quality palliative-care guidance |
| Oral Health & Dental Services | 6 new | Service Delivery | WHO oral-health action plan |
| Eye Health Services | 6 new | Service Delivery | WHO Package of Eye Care Interventions |
| Antimicrobial Stewardship | 6 new | Safety & Quality | WHO facility antimicrobial-stewardship toolkit |
| Outbreak Preparedness & Response | 6 new | Safety & Quality | WHO facility outbreak-readiness toolkit |
| Neglected Tropical Disease Services | 6 new | Service Delivery | WHO NTD road map 2021–2030 |
| Disability & Inclusion | 6 new | Person-Centredness & Community | WHO disability-inclusive health-services toolkit |
| Health Promotion & Education | 6 new | Person-Centredness & Community | WHO HHFA |
| Environment & Climate Resilience | 6 new | Resources & Infrastructure | WHO climate-resilient health-care facility guidance |
| Staff Health & Safety | 6 new | Workforce & Capability | WHO/ILO occupational safety guidance for health workers |
| Finance, Billing & Insurance Claims | 10 existing | Financing & Resource Management | WHO HHFA management and finance |

## Projected comprehensive releases

Published releases are immutable. Approval will therefore create successor releases rather
than changing releases used by existing assessments.

| Facility profile | Current published release | Successor | Published departments after approval | Available questions after approval |
|---|---|---|---:|---:|
| General Hospital | `VYTTE_HOSPITAL_READINESS_V2` | `VYTTE_HOSPITAL_READINESS_V3` | 33 | 334 |
| Primary Health Centre | `VYTTE_PHC_ASSESSMENT_V2` | `VYTTE_PHC_ASSESSMENT_V3` | 25 | 280 |
| Clinic | `VYTTE_CLINIC_ASSESSMENT_V1` | `VYTTE_CLINIC_ASSESSMENT_V2` | 16 | 218 |

Question totals are the maximum when every available department is selected. Required and
default applicability still controls what starts selected, and optional departments remain
off until the user chooses them.

The same department framework is reused across every compatible facility profile. New V1
comprehensive releases will be prepared for the other mapped facility profiles so the work
does not create parallel copies of the same questions.

## Facility-profile mapping correction

Three published profiles currently have no department map and therefore cannot receive a
comprehensive release:

- Federal Medical Centre
- Cottage or Rural Hospital
- Comprehensive Health Centre

Approval includes mapping them through the existing profile policy:

- Federal Medical Centre: tertiary/general-hospital core with advanced departments optional.
- Cottage or Rural Hospital: district/health-centre core with higher-acuity departments optional.
- Comprehensive Health Centre: PHC/health-centre core with maternity, laboratory and community services default.

## Review questions for the human approver

For each department, confirm:

1. The six questions represent a defensible minimum readiness set.
2. Wording fits the facility levels where the department is offered.
3. National regulation does not require a narrower or stronger condition.
4. `Yes / Partially / No` is appropriate for the initial beta score.
5. No proposed item should be unscored context or an explicit critical failure.
6. The cited source is suitable and the Vytte wording does not reproduce protected text.

## Publication sequence after approval

1. Register the source records and URLs idempotently.
2. Create and publish 150 question identities and immutable first versions.
3. Create and publish 27 department framework versions (25 new sets, plus Emergency and Finance reuse).
4. Add the three missing facility-profile mappings.
5. Publish successor releases for General Hospital, PHC and Clinic.
6. Publish V1 comprehensive releases for every other mapped health-facility profile.
7. Verify zero duplicate question identities in every release.
8. Run a fresh PostgreSQL official seed, focused creation tests and the full sequential suite.
9. Back up production, deploy, seed, and verify that no `coming soon` department remains.

## Source links

- WHO Harmonized Health Facility Assessment: https://www.who.int/data/data-collection-tools/harmonized-health-facility-assessment
- WHO Emergency Care Toolkit: https://www.who.int/teams/integrated-health-services/clinical-services-and-systems/emergency-and-critical-care/emergency-care-toolkit
- WHO emergency, critical and operative care resources: https://www.who.int/teams/integrated-health-services/clinical-services-and-systems/emergency--critical-and-operative-care/essential-resources-for-emergency--critical-and-operative-care
- WHO safe surgery tools: https://www.who.int/teams/integrated-health-services/quality-of-care-and-patient-safety/patient-safety-guidance-and-tools/safe-surgery/tool-and-resources
- WHO maternal and newborn quality standards: https://www.who.int/publications/i/item/9789241511216
- WHO postnatal care recommendations: https://www.who.int/publications/i/item/9789240045989
- WHO family-planning handbook: https://www.who.int/publications/i/item/family-planning---a-global-handbook-for-providers
- WHO PEN: https://www.who.int/publications/b/54566
- WHO/UNAIDS adolescent standards: https://www.who.int/publications/i/item/9789241549332/
- WHO rehabilitation guide for action: https://www.who.int/publications/i/item/rehabilitation-in-health-systems-guide-for-action
- WHO palliative-care quality guidance: https://www.who.int/publications/i/item/9789240035164
- WHO antimicrobial-stewardship toolkit: https://www.who.int/publications-detail-redirect/9789241515481
- WHO disability-inclusive health-services toolkit: https://www.who.int/publications/i/item/9789290618928
- WHO ICOPE: https://www.who.int/publications/i/item/9789241515993
- WHO Package of Eye Care Interventions: https://www.who.int/publications/i/item/9789240048959
- WHO oral-health action plan: https://www.who.int/publications/i/item/9789240090538/
- WHO facility outbreak-readiness toolkit: https://www.who.int/publications/i/item/9789240051027
- WHO climate-resilient health-care facility guidance: https://www.who.int/publications/i/item/9789240012226
- WHO/ILO occupational safety guidance for health workers: https://www.who.int/publications-detail-redirect/9789240040779
- WHO NTD road map: https://www.who.int/publications/b/53673
- WHO blood safety and availability: https://www.who.int/en/news-room/fact-sheets/detail/blood-safety-and-availability
- IAEA diagnostic-radiology quality control: https://www.iaea.org/publications/14890/handbook-of-basic-quality-control-tests-for-diagnostic-radiology
