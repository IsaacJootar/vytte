<?php

namespace App\Services\Reporting;

/**
 * Turns a programme report's findings into a short action plan: for each weak answer, what to do,
 * how to go about it, who does it and by when.
 *
 * Pure and read-only. It reads only the finding codes already in the frozen report and a fixed
 * library of actions, so it adds nothing to what was collected and never changes a stored result.
 * It is written for the person who runs the place (the PHC in-charge or the head teacher), in plain
 * words, and it is the same for every place so reports can be compared.
 *
 * Findings are keyed by the question's code suffix (B1, D6, F3 ...), which is the same in the School
 * and the PHC template. Wording that differs between the two uses the tokens {lead}, {team}, {place}
 * and {girls}.
 */
class ProgrammeActionPlan
{
    /** The most actions one report lists, so the plan stays something a person can actually do. */
    public const MAX_ACTIONS = 10;

    public const HORIZONS = [
        'now' => 'In the first 30 days',
        'soon' => 'Within 3 months',
        'later' => 'Within 6 to 12 months',
    ];

    /** The four must-haves. Until each is in place a Dignity Bank cannot start. */
    public const MUST_HAVES = ['D1', 'F1', 'F2', 'F3'];

    /**
     * Words that change between the two kinds of place.
     *
     * @return array<string, string>
     */
    private function tokens(string $noun): array
    {
        return $noun === 'school'
            ? [
                '{lead}' => 'the head teacher',
                '{team}' => 'the senior woman teacher',
                '{place}' => 'school',
                '{girls}' => 'girls',
                '{support}' => 'the Local Government Education Authority or SUBEB',
                '{group}' => "the school-based management committee or PTA",
                '{slot}' => 'a school assembly, a health club or a class period',
            ]
            : [
                '{lead}' => 'the PHC in-charge',
                '{team}' => 'the nurse or midwife on duty',
                '{place}' => 'PHC',
                '{girls}' => 'girls and women',
                '{support}' => 'the LGA PHC coordinator or the Primary Health Care Board',
                '{group}' => 'the PHC management committee or ward development committee',
                '{slot}' => 'a clinic day, an antenatal session or an adolescent clinic day',
            ];
    }

    /**
     * The library for one kind of place: an entry that is worded differently for a school carries a
     * `school` override, which replaces its what, how and who when the place is a school.
     *
     * @return array<string, array{horizon: string, what: string, how: array<int, string>, who: string}>
     */
    private function libraryFor(string $noun): array
    {
        return array_map(function (array $entry) use ($noun) {
            $override = $entry['school'] ?? null;
            unset($entry['school']);

            return $noun === 'school' && $override ? $override + $entry : $entry;
        }, $this->library());
    }

    /** @return array<string, array{horizon: string, what: string, how: array<int, string>, who: string, school?: array<string, mixed>}> */
    private function library(): array
    {
        return [
            // ---- products
            'B1' => ['horizon' => 'now', 'what' => 'Keep menstrual products in stock for emergencies.',
                'how' => ['Keep a small stock of pads in one named, easy-to-reach place.', 'Write down what is used so you know when to top up.'],
                'who' => '{lead}, with {team}'],
            'B3' => ['horizon' => 'now', 'what' => 'Set up one clear way for {girls} to get an emergency product.',
                'how' => ['Name one person and one place to ask, and tell every girl and staff member.', 'Give the product without questions or payment.'],
                'who' => '{team}'],
            'B4' => ['horizon' => 'soon', 'what' => 'Agree a reliable way to restock when products run out.',
                'how' => ['Agree a source: a Dignity Bank supplier, {group}, a partner or a local group.', 'Check stock once a month and ask before it runs out.'],
                'who' => '{lead}, with {group}'],
            'B5' => ['horizon' => 'now', 'what' => 'Stop products running out.',
                'how' => ['Count stock every week and agree a minimum level.', 'Ask for more as soon as stock reaches that level.'],
                'who' => '{team}'],
            // ---- information, services and referral
            'C1' => ['horizon' => 'soon', 'what' => 'Give menstrual health information regularly.',
                'how' => ['Add a short session to a fixed slot ({slot}).', 'Use simple leaflets and keep a list of who attended.'],
                'who' => '{team}'],
            'C3' => ['horizon' => 'soon', 'what' => 'Get menstrual health materials and use them.',
                'how' => ['Ask {support}, or a partner, for leaflets and posters.', 'Display them where {girls} wait or gather, and use them in sessions.'],
                'who' => '{lead}'],
            'C4' => ['horizon' => 'soon', 'what' => 'Train a staff member in menstrual and adolescent health.',
                'how' => ['Ask {support} for a place on a training, or invite a partner to train on site.', 'Train one or two people, then let them share with the rest of the staff.'],
                'who' => '{lead}, with {support}'],
            'C5' => ['horizon' => 'soon', 'what' => 'Make help available for menstrual pain and problems.',
                'how' => ['Make it a normal part of routine visits, with a set day and time.', 'Make sure the person giving help knows when to refer.'],
                'who' => '{lead}, with {team}',
                // In a school this question is about a trusted adult, not a clinic visit.
                'school' => ['what' => 'Name a trusted adult girls can go to about menstrual concerns.',
                    'how' => ['Choose a woman teacher or counsellor that girls trust, and tell every class who she is.', 'Give her a private place and a set time, and make sure she knows when to refer a girl to a health worker.'],
                    'who' => '{lead}, with {team}'],
            ],
            'C6' => ['horizon' => 'soon', 'what' => 'Make support for adolescent girls confidential.',
                'how' => ['Use a private corner or room, or a screen, for these visits.', 'Agree with all staff that what a girl says stays private.'],
                'who' => '{lead}'],
            'C7' => ['horizon' => 'soon', 'what' => 'Set up a working referral route.',
                'how' => ['List the nearest facility that can handle problems the PHC cannot.', 'Agree who to call, give a referral note, and check later that the person was seen.'],
                'who' => '{lead}, with {support}'],
            // ---- toilets, water and safe disposal
            'D1' => ['horizon' => 'now', 'what' => 'Provide a private toilet that {girls} can use.',
                'how' => ['Set aside one toilet for {girls} only and mark it clearly.', 'If none can be set aside, ask {support} to include a new one in the next works plan.'],
                'who' => '{lead}, with {support}'],
            'D2' => ['horizon' => 'now', 'what' => "Repair the {girls}' toilet so it works.",
                'how' => ['List what is broken and what it will cost.', 'Approve small repairs from the {place} budget; send bigger ones to {support}.'],
                'who' => '{lead}'],
            'D3' => ['horizon' => 'now', 'what' => 'Fix the toilet door so it closes and locks.',
                'how' => ['Ask a local carpenter to fit a working door and lock.'],
                'who' => '{lead}'],
            'D4' => ['horizon' => 'now', 'what' => 'Make the toilet private enough to change menstrual materials.',
                'how' => ['Add a screen or a solid door, a hook and a small shelf.', 'Ask a few girls and women if it now feels private.'],
                'who' => '{lead}'],
            'D5' => ['horizon' => 'now', 'what' => 'Provide water at the toilet.',
                'how' => ['Put a container with a tap or a bucket with a cup in the toilet.', 'Assign someone to refill it every day.'],
                'who' => '{lead}'],
            'D6' => ['horizon' => 'now', 'what' => 'Provide soap at the handwashing point.',
                'how' => ['Buy soap and put it where hands are washed.', 'Check it every week and replace it before it finishes.'],
                'who' => '{lead}'],
            'D7' => ['horizon' => 'now', 'what' => 'Put a covered bin in the toilet for used menstrual materials.',
                'how' => ['Place a bin with a lid in the toilet and empty it on a schedule.'],
                'who' => '{lead}'],
            'D8' => ['horizon' => 'soon', 'what' => 'Dispose of used menstrual materials safely.',
                'how' => ['Agree one safe way to get rid of them (sealed collection and burning or burying, as {support} advises).', 'Show the cleaners how, and write the routine down.'],
                'who' => '{lead}, with {support}'],
            'D9' => ['horizon' => 'now', 'what' => 'Keep the toilet clean.',
                'how' => ['Make a cleaning rota with names and days.', 'Check it once a week and make sure cleaning supplies are available.'],
                'who' => '{lead}'],
            'D10' => ['horizon' => 'later', 'what' => 'Make a toilet usable by someone with limited mobility.',
                'how' => ['Plan a ramp, a wider door and a grab rail for one toilet.', 'Include it in the next works request to {support}.'],
                'who' => '{lead}, with {support}'],
            // ---- ready to host a Dignity Bank
            'F1' => ['horizon' => 'now', 'what' => 'Win the support of the leaders for a Dignity Bank.',
                'how' => ['Hold a short meeting with {lead} and {group}.', 'Show them this report and agree support in writing.'],
                'who' => 'The programme team, with {lead}'],
            'F2' => ['horizon' => 'now', 'what' => 'Find a willing person to run the Dignity Bank.',
                'how' => ['Ask for a volunteer or choose {team} as the focal person.', 'Agree what the role involves and how much time it takes.'],
                'who' => '{lead}'],
            'F3' => ['horizon' => 'now', 'what' => 'Provide a secure place to store menstrual products.',
                'how' => ['Provide a lockable cupboard or room and name who holds the key.'],
                'who' => '{lead}'],
            'F4' => ['horizon' => 'soon', 'what' => 'Set up a private place where {girls} can collect products unnoticed.',
                'how' => ['Choose a spot away from public view and agree how {girls} ask for products.'],
                'who' => '{lead}, with {team}'],
            'F5' => ['horizon' => 'soon', 'what' => 'Make sure {girls} can reach the collection place whenever they need it.',
                'how' => ['Agree opening times, or a key-holder, so products can be reached during the day without asking permission.'],
                'who' => '{lead}'],
            'F6' => ['horizon' => 'soon', 'what' => 'Agree to keep simple records of products given out.',
                'how' => ['Use a simple register: date, number of products, and the running stock.', 'Look at it with {lead} every month.'],
                'who' => 'The Dignity Bank focal person'],
            'F7' => ['horizon' => 'soon', 'what' => 'Set up a way to ask for more stock when it runs low.',
                'how' => ['Agree who to ask (the supplier or partner) and how, and write the contact down.'],
                'who' => '{lead}'],
            'F8' => ['horizon' => 'soon', 'what' => 'Form or strengthen a group to support the Dignity Bank.',
                'how' => ['Invite {group}, a women\'s group or local leaders to back it.', 'Agree who does what and when the group will meet.'],
                'who' => '{lead}, with {group}'],
        ];
    }

    /**
     * @param  array<int, array{code: string, score: float, role: ?string, gap: string}>  $weakItems  every answer scored below strong
     * @param  array<int, string>  $missingMustHaveCodes  must-have codes that were reported missing
     * @return array{horizons: array<int, array{key: string, label: string, actions: array<int, array<string, mixed>>}>, total: int, shown: int, more: int}
     */
    public function forFindings(array $weakItems, array $missingMustHaveCodes, string $noun): array
    {
        $library = $this->libraryFor($noun);
        $tokens = $this->tokens($noun);
        $fill = fn (string $text) => strtr($text, $tokens);

        $candidates = collect($weakItems)
            ->filter(fn (array $item) => isset($library[$item['code']]))
            ->map(function (array $item) use ($library, $missingMustHaveCodes) {
                $entry = $library[$item['code']];

                return $entry + [
                    'code' => $item['code'],
                    'score' => (float) $item['score'],
                    'finding' => $item['gap'],
                    'must_have' => in_array($item['code'], $missingMustHaveCodes, true),
                ];
            });

        // A missing must-have comes first, then the worst scores.
        $ranked = $candidates->sortBy([
            fn ($a, $b) => (int) $b['must_have'] <=> (int) $a['must_have'],
            fn ($a, $b) => $a['score'] <=> $b['score'],
        ])->values();

        $chosen = $ranked->take(self::MAX_ACTIONS);

        $horizons = [];
        foreach (self::HORIZONS as $key => $label) {
            $actions = $chosen->where('horizon', $key)->values()->map(fn (array $action) => [
                'code' => $action['code'],
                'finding' => $action['finding'],
                'must_have' => $action['must_have'],
                'what' => ucfirst($fill($action['what'])),
                'how' => array_map(fn ($step) => ucfirst($fill($step)), $action['how']),
                'who' => ucfirst($fill($action['who'])),
                'when' => $label,
            ])->all();

            if ($actions !== []) {
                $horizons[] = ['key' => $key, 'label' => $label, 'actions' => $actions];
            }
        }

        return [
            'horizons' => $horizons,
            'total' => $ranked->count(),
            'shown' => $chosen->count(),
            'more' => max(0, $ranked->count() - $chosen->count()),
        ];
    }

    /** The one-line action for a finding code, used where only a short list is wanted (the programme portfolio). */
    public function headline(string $code, string $noun): ?string
    {
        $entry = $this->libraryFor($noun)[$code] ?? null;

        return $entry ? ucfirst(strtr($entry['what'], $this->tokens($noun))) : null;
    }
}
