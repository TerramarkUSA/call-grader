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
                'training_reference' => 'Take control from the start. Set the agenda, get permission to ask questions. The prospect gains control when they\'re asking the questions - flip it back with "Great question, and I\'ll get to that. First, let me ask..."',
                'scoring_criteria' => [
                    '4' => 'Excellent opening with name, company, and immediately takes control with a question. Never loses control.',
                    '3' => 'Good opening, takes control, but may have briefly lost it once.',
                    '2' => 'Weak opening or let prospect lead for portions of the call.',
                    '1' => 'No real opening, prospect controlled the entire conversation.',
                ],
            ],
            [
                'external_id' => 'discovery',
                'name' => 'Be a Good Detective',
                'weight' => 0.15,
                'sort_order' => 2,
                'training_reference' => 'Give information to get information. Ask about timeline, budget, who else is involved in the decision. The more you know, the better you can match them to the right property.',
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
                'training_reference' => 'Paint the picture of ownership. Weekend getaways, family gatherings, building equity. What does their ideal property look like? What would they DO there?',
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
                'training_reference' => 'Create urgency without pressure. Limited availability, others looking, prices going up. "These lots don\'t last long at this price point."',
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
                'training_reference' => 'Focus on the VALUE, not the price. What they GET for what they pay. Compare to alternatives. Payment plans, financing options.',
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
                'training_reference' => 'The ONLY goal is the appointment. "The only way to know if this is right for you is to come see it in person." Stop selling once they agree to come.',
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
                'training_reference' => 'Build trust in the company. How long in business, number of happy owners, BBB rating, developments completed. They\'re not buying from you yet, they\'re buying from the company.',
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
                'training_reference' => 'Energy and attitude matter. Smile when you talk - they can hear it. Match their pace but lead with enthusiasm. Be the person they want to meet.',
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
                'external_id' => 'handled_location_properly',
                'name' => 'Handled location properly',
                'sort_order' => 1,
            ],
            [
                'external_id' => 'asked_discovery_questions',
                'name' => 'Asked discovery questions',
                'sort_order' => 2,
            ],
            [
                'external_id' => 'captured_contact_info',
                'name' => 'Captured contact info',
                'sort_order' => 3,
            ],
            [
                'external_id' => 'gave_got_information',
                'name' => 'Gave information, Got information',
                'sort_order' => 4,
            ],
            [
                'external_id' => 'asked_for_appointment',
                'name' => 'Asked for appointment',
                'sort_order' => 5,
            ],
            [
                'external_id' => 'explained_full_sale',
                'name' => 'Explained full sale',
                'sort_order' => 6,
            ],
            [
                'external_id' => 'confirmed_next_steps',
                'name' => 'Confirmed next steps',
                'sort_order' => 7,
            ],
            [
                'external_id' => 'sold_company',
                'name' => 'Sold Company',
                'sort_order' => 8,
            ],
        ];

        foreach ($positiveCheckpoints as $checkpoint) {
            RubricCheckpoint::create(array_merge($checkpoint, ['type' => 'positive']));
        }

        // 4 Negative Checkpoints
        $negativeCheckpoints = [
            [
                'external_id' => 'product_vomit',
                'name' => 'Product vomit',
                'sort_order' => 1,
            ],
            [
                'external_id' => 'over_informed',
                'name' => 'Over-informed',
                'sort_order' => 2,
            ],
            [
                'external_id' => 'lost_control',
                'name' => 'Lost control',
                'sort_order' => 3,
            ],
            [
                'external_id' => 'talked_past_close',
                'name' => 'Talked past the close',
                'sort_order' => 4,
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
