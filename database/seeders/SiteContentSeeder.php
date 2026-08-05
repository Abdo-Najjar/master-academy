<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Initial public-site content, matching the copy the marketing site launched
 * with. Uses firstOrCreate so re-seeding never duplicates records the admin has
 * since edited.
 */
class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPrograms();
        $this->seedTestimonials();
    }

    private function seedPrograms(): void
    {
        $programs = [
            [
                'title' => ['ar' => 'دبلوم إعداد مصور محترف', 'en' => 'Professional Photographer Diploma'],
                'description' => ['ar' => 'تصوير بالكاميرا والجوال، إضاءة، ريلز ومونتاج ضمن تطبيقات واقعية.', 'en' => 'Camera and mobile photography, lighting, reels and editing through real-world projects.'],
                'category' => 'creative',
                'icon' => '◉',
                'duration' => ['ar' => '3 أشهر', 'en' => '3 months'],
                'price' => ['ar' => '160 شيكل شهريًا', 'en' => '160 ILS monthly'],
                'branches_label' => ['ar' => '3 فروع', 'en' => '3 branches'],
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'title' => ['ar' => 'AI Content Master', 'en' => 'AI Content Master'],
                'description' => ['ar' => 'تصميم صور وفيديوهات ومحتوى تسويقي بأحدث أدوات الذكاء الاصطناعي.', 'en' => 'Create images, videos and marketing content with the latest AI tools.'],
                'category' => 'creative',
                'icon' => '✦',
                'duration' => ['ar' => '10 لقاءات', 'en' => '10 sessions'],
                'price' => ['ar' => '250 شيكل', 'en' => '250 ILS'],
                'branches_label' => ['ar' => '3 فروع', 'en' => '3 branches'],
                'is_featured' => false,
                'sort_order' => 2,
            ],
            [
                'title' => ['ar' => 'دبلوم فني الطاقة الشمسية', 'en' => 'Solar Energy Technician Diploma'],
                'description' => ['ar' => 'تصميم وتركيب الأنظمة الشمسية من الأساسيات حتى المشاريع التطبيقية.', 'en' => 'Designing and installing solar systems from fundamentals to applied projects.'],
                'category' => 'technical',
                'icon' => '☀',
                'duration' => ['ar' => '3 أشهر', 'en' => '3 months'],
                'price' => ['ar' => '180 شيكل شهريًا', 'en' => '180 ILS monthly'],
                'branches_label' => ['ar' => '3 فروع', 'en' => '3 branches'],
                'is_featured' => false,
                'sort_order' => 3,
            ],
            [
                'title' => ['ar' => 'مهارات التمريض والإسعاف الأولي', 'en' => 'Nursing and First Aid Skills'],
                'description' => ['ar' => 'تدريب عملي على العلامات الحيوية والحقن والتعامل مع الحالات الطارئة.', 'en' => 'Hands-on training in vital signs, injections and handling emergencies.'],
                'category' => 'professional',
                'icon' => '+',
                'duration' => ['ar' => '6 لقاءات', 'en' => '6 sessions'],
                'price' => ['ar' => '99 شيكل', 'en' => '99 ILS'],
                'branches_label' => ['ar' => '3 فروع', 'en' => '3 branches'],
                'is_featured' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($programs as $program) {
            Program::query()->firstOrCreate(
                ['title->ar' => $program['title']['ar']],
                $program
            );
        }
    }

    private function seedTestimonials(): void
    {
        $testimonials = [
            [
                'name' => ['ar' => 'أحمد أبو مصطفى', 'en' => 'Ahmed Abu Mustafa'],
                'role' => ['ar' => 'خريج دبلوم الطاقة الشمسية', 'en' => 'Solar Energy Diploma graduate'],
                'quote' => ['ar' => 'التدريب العملي أعطاني ثقة أبدأ شغل حقيقي، والمدرب كان يتابع معنا خطوة بخطوة.', 'en' => 'The hands-on training gave me the confidence to start real work, and the trainer followed up with us step by step.'],
                'sort_order' => 1,
            ],
            [
                'name' => ['ar' => 'سارة النجار', 'en' => 'Sara Al-Najjar'],
                'role' => ['ar' => 'خريجة دبلوم التصوير', 'en' => 'Photography Diploma graduate'],
                'quote' => ['ar' => 'من أول لقاء بدأت أطبّق، وفي نهاية الدبلوم صار عندي معرض أعمال أقدر أقدمه للعملاء.', 'en' => 'I started applying from the first session, and by the end I had a portfolio to show clients.'],
                'sort_order' => 2,
            ],
            [
                'name' => ['ar' => 'محمود الشاعر', 'en' => 'Mahmoud Al-Shaer'],
                'role' => ['ar' => 'خريج برنامج مهني', 'en' => 'Vocational program graduate'],
                'quote' => ['ar' => 'المحتوى واضح وقريب من سوق العمل، وأكثر شيء ميّز التجربة هو التطبيق والمتابعة.', 'en' => 'The content is clear and close to the job market; what stood out most was the practice and follow-up.'],
                'sort_order' => 3,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::query()->firstOrCreate(
                ['name->ar' => $testimonial['name']['ar']],
                $testimonial
            );
        }
    }
}
