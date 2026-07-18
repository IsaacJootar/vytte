# Seed Dataset Manifest

## Authority

Seed data exists to make development, tests, and demos reproducible. It is not production clinical authority.

The default seed sequence is:

1. `PlatformSettingsSeeder`
2. `ReferenceDataSeeder`
3. `PlatformGovernedDemoSeeder`
4. `PlanFeatureSeeder`
5. `DemoAccountSeeder`
6. `DemoDataSeeder`

The default seed is repository-contained. It does not read personal Downloads folders or external documents.

## Governed Demonstration Dataset

`PlatformGovernedDemoSeeder` creates a clearly labelled demonstration catalogue:

- facility profiles: Clinic, Primary Health Centre, General Hospital;
- official demo departments: Outpatient, Pharmacy, Laboratory, Mental Health;
- department framework version 1 for each demo department;
- one comprehensive Clinic catalogue release;
- one focused Mental Health catalogue release.

The demonstration catalogue proves the architecture end to end. It must not be described as production clinical methodology.

## Publication Rule

Official production content requires:

- source authority;
- licence/provenance metadata;
- completed scoring profile;
- supported response types;
- evidence and critical-failure rules where applicable;
- curator review;
- immutable framework version publication;
- immutable catalogue release publication.

## Response Types

Currently publishable response types:

- scalar option questions;
- open text when explicitly unscored;
- numeric questions with valid input configuration.

Scored numeric questions require frozen numeric bands. Declaring a question type in the database does not make it publishable.

## Counts

Counts are dataset metadata, not architecture constants. They may change through governed dataset updates.
