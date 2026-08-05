<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.hero_eyebrow', 'مركز تدريب مهني معتمد');
        $this->migrator->add('site.hero_title', 'مهارة اليوم.');
        $this->migrator->add('site.hero_title_highlight', 'فرصة الغد.');
        $this->migrator->add('site.hero_lead', 'تدريب عملي معتمد يجهّزك بثقة لسوق العمل.');
        $this->migrator->add('site.hero_badge_title', 'برامج وشهادات معتمدة');
        $this->migrator->add('site.hero_badge_note', 'تدريب تطبيقي بإشراف مدربين متخصصين');

        $this->migrator->add('site.stats', [
            ['value' => '+1,000', 'label' => 'متدرب خرّجناهم بمهارات عملية جاهزة لسوق العمل'],
            ['value' => '+85%', 'label' => 'من خريجينا بدأوا العمل أو طوّروا مسارهم بعد التدريب'],
            ['value' => '3', 'label' => 'فروع تخدم المتدربين في غزة ودير البلح وخانيونس'],
        ]);

        $this->migrator->add(
            'site.about_text',
            'ماستر أكاديمي مركز تدريب وتطوير مهني معتمد، نصمم برامج قصيرة وعملية في المجالات المطلوبة، ونربط المتدرب بالخبرة والأدوات التي يحتاجها ليبدأ بثقة.'
        );

        $this->migrator->add('site.about_values', [
            'تطبيق عملي',
            'مدربون متخصصون',
            'شهادات معتمدة',
            'متابعة بعد التدريب',
        ]);

        $this->migrator->add('site.director_name', 'المهندس محمد العصار');
        $this->migrator->add('site.director_role', 'مدير عام ماستر أكاديمي');
        $this->migrator->add(
            'site.director_quote',
            'في ماستر أكاديمي نؤمن أن التدريب الحقيقي لا يُقاس بعدد الساعات، بل بالفرق الذي يصنعه في حياة المتدرب. لذلك نبني برامجنا حول التطبيق العملي واحتياجات سوق العمل، ونرافق كل متدرب حتى تتحول المعرفة إلى مهارة، والمهارة إلى فرصة.'
        );

        $this->migrator->add('site.contact_phone', '+970598782218');
        $this->migrator->add('site.contact_whatsapp', '970598782218');
        $this->migrator->add('site.license_number', '58631');
    }
};
