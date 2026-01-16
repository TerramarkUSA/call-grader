<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RubricCategory;
use App\Models\RubricCheckpoint;
use App\Models\ObjectionType;

class RubricSeeder extends Seeder
{
    public function run(): void
    {
        // 8 Categories
        $categories = [
            [
                'external_id' => 'opening_control',
                'name' => 'Opening & Control',
                'weight' => 0.15,
                'sort_order' => 1,
                'scoring_criteria' => [
                    '4' => 'Excellent opening with name, company, and immediately takes control with a question. Never loses control.',
                    '3' => 'Good opening, takes control, but may have briefly lost it once.',
                    '2' => 'Weak opening or let prospect lead for portions of the call.',
                    '1' => 'No real opening, prospect controlled the entire conversation.',
                ],
            ],
            [
                'external_id' => 'discovery',
                'name' => 'Discovery & Qualification',
                'weight' => 0.15,
                'sort_order' => 2,
                'scoring_criteria' => [
                    '4' => 'Asked 3+ open-ended questions, uncovered timeline, motivation, and decision-makers.',
                    '3' => 'Asked good questions, got some useful info but missed an area.',
                    '2' => 'Surface-level questions only, didn\'t dig into motivation or timeline.',
                    '1' => 'No real discovery, jumped straight to pitch.',
                ],
            ],
            [
                'external_id' => 'hope_of_gain',
                'name' => 'Hope of Gain',
                'weight' => 0.10,
                'sort_order' => 3,
                'scoring_criteria' => [
                    '4' => 'Painted vivid picture of ownership benefits tied to prospect\'s stated desires.',
                    '3' => 'Mentioned benefits but didn\'t personalize to prospect.',
                    '2' => 'Generic benefits, no emotional connection.',
                    '1' => 'No hope of gain presented.',
                ],
            ],
            [
                'external_id' => 'fear_of_loss',
                'name' => 'Fear of Loss / Urgency',
                'weight' => 0.10,
                'sort_order' => 4,
                'scoring_criteria' => [
                    '4' => 'Created genuine urgency with specific scarcity, deadlines, or consequences of waiting.',
                    '3' => 'Mentioned urgency but didn\'t make it feel real.',
                    '2' => 'Weak urgency, easily dismissed.',
                    '1' => 'No urgency created.',
                ],
            ],
            [
                'external_id' => 'sell_the_deal',
                'name' => 'Sell the Deal / Value',
                'weight' => 0.15,
                'sort_order' => 5,
                'scoring_criteria' => [
                    '4' => 'Made the deal feel special, exclusive, and valuable. Prospect understands why NOW.',
                    '3' => 'Explained value but didn\'t make it feel urgent or special.',
                    '2' => 'Weak value proposition, generic pricing discussion.',
                    '1' => 'No value selling, just stated features.',
                ],
            ],
            [
                'external_id' => 'sold_appointment',
                'name' => 'Sold the Appointment',
                'weight' => 0.20,
                'sort_order' => 6,
                'scoring_criteria' => [
                    '4' => 'Clearly asked for appointment, handled objections, confirmed date/time, gave clear next steps.',
                    '3' => 'Asked for appointment, got commitment, but some details fuzzy.',
                    '2' => 'Weakly asked for appointment or let prospect dictate terms.',
                    '1' => 'Never clearly asked for the appointment.',
                ],
            ],
            [
                'external_id' => 'company_credibility',
                'name' => 'Company & Credibility',
                'weight' => 0.10,
                'sort_order' => 7,
                'scoring_criteria' => [
                    '4' => 'Established company credibility naturally, used social proof effectively.',
                    '3' => 'Mentioned company strengths but didn\'t weave in naturally.',
                    '2' => 'Minimal credibility building.',
                    '1' => 'No credibility established, prospect may doubt legitimacy.',
                ],
            ],
            [
                'external_id' => 'professionalism_tone',
                'name' => 'Professionalism & Tone',
                'weight' => 0.05,
                'sort_order' => 8,
                'scoring_criteria' => [
                    '4' => 'Professional, friendly, confident. Good energy throughout.',
                    '3' => 'Professional but could have had more energy or warmth.',
                    '2' => 'Some unprofessional moments or flat energy.',
                    '1' => 'Unprofessional, rude, or completely disengaged.',
                ],
            ],
        ];

        foreach ($categories as $category) {
            RubricCategory::create($category);
        }

        // 8 Positive Checkpoints
        $positiveCheckpoints = [
            [
                'external_id' => 'asked_discovery_first',
                'name' => 'Asked open-ended discovery question before giving information',
                'sort_order' => 1,
            ],
            [
                'external_id' => 'captured_contact',
                'name' => 'Captured contact information (name, phone, email, address)',
                'sort_order' => 2,
            ],
            [
                'external_id' => 'give_get_info',
                'name' => 'Give information, get information (traded value for value)',
                'sort_order' => 3,
            ],
            [
                'external_id' => 'asked_for_appointment',
                'name' => 'Asked for the appointment at least once',
                'sort_order' => 4,
            ],
            [
                'external_id' => 'explained_full_sale',
                'name' => 'Explained the full sale with all timeslots',
                'sort_order' => 5,
            ],
            [
                'external_id' => 'established_datetime',
                'name' => 'Established date/time for appointment',
                'sort_order' => 6,
            ],
            [
                'external_id' => 'clear_next_steps',
                'name' => 'Gave clear directions or confirmed next steps',
                'sort_order' => 7,
            ],
            [
                'external_id' => 'clean_close',
                'name' => 'Got off the phone cleanly (didn\'t oversell after booking)',
                'sort_order' => 8,
            ],
        ];

        foreach ($positiveCheckpoints as $checkpoint) {
            RubricCheckpoint::create(array_merge($checkpoint, ['type' => 'positive']));
        }

        // 5 Negative Checkpoints
        $negativeCheckpoints = [
            [
                'external_id' => 'product_vomit',
                'name' => 'Product vomit—dumped features/amenities without asking questions first',
                'sort_order' => 1,
            ],
            [
                'external_id' => 'offered_price_unsolicited',
                'name' => 'Offered price range and acreage range without customer request',
                'sort_order' => 2,
            ],
            [
                'external_id' => 'gave_too_much_info',
                'name' => 'Gave so much information there was no reason to visit',
                'sort_order' => 3,
            ],
            [
                'external_id' => 'lost_control',
                'name' => 'Let prospect control the call—reactive the entire time',
                'sort_order' => 4,
            ],
            [
                'external_id' => 'talked_past_close',
                'name' => 'Kept talking after appointment was set (talked themselves out of it)',
                'sort_order' => 5,
            ],
        ];

        foreach ($negativeCheckpoints as $checkpoint) {
            RubricCheckpoint::create(array_merge($checkpoint, ['type' => 'negative']));
        }

        // Objection Types
        $objectionTypes = [
            ['name' => 'Price / Budget', 'category' => 'Financial', 'sort_order' => 1],
            ['name' => 'Timing / Not Ready', 'category' => 'Timing', 'sort_order' => 2],
            ['name' => 'Need to Consult Spouse/Partner', 'category' => 'Decision', 'sort_order' => 3],
            ['name' => 'Need to Think About It', 'category' => 'Decision', 'sort_order' => 4],
            ['name' => 'Location Concerns', 'category' => 'Property', 'sort_order' => 5],
            ['name' => 'Already Own Property', 'category' => 'Situation', 'sort_order' => 6],
            ['name' => 'Bad Past Experience', 'category' => 'Trust', 'sort_order' => 7],
            ['name' => 'Too Good to Be True', 'category' => 'Trust', 'sort_order' => 8],
            ['name' => 'Just Looking / Not Serious', 'category' => 'Intent', 'sort_order' => 9],
            ['name' => 'Other', 'category' => 'Other', 'sort_order' => 99],
        ];

        foreach ($objectionTypes as $type) {
            ObjectionType::create($type);
        }
    }
}
