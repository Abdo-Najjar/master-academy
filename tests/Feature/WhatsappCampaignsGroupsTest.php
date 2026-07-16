<?php

use App\Filament\Admin\Resources\StudentGroups\Pages\ManageStudentGroups;
use App\Filament\Admin\Resources\WhatsappCampaigns\Pages\ListWhatsappCampaigns;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\User;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use App\Services\WhatsappCampaignService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::firstOrCreate(
        ['email' => 'admin@ma.test'],
        ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()]
    );
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->student1 = Student::create(['name' => 'طالب أول', 'student_number' => 'STU-CMP-1', 'whatsapp_number' => '972501111111']);
    $this->student2 = Student::create(['name' => 'طالب ثاني', 'student_number' => 'STU-CMP-2', 'phone_number' => '972502222222']);
    $this->student3 = Student::create(['name' => 'طالب بلا هاتف', 'student_number' => 'STU-CMP-3']);
});

it('creates a student group with students via the admin resource', function () {
    Livewire::test(ManageStudentGroups::class)
        ->callAction('create', data: [
            'name' => 'مجموعة الاختبار',
            'students' => [$this->student1->id, $this->student2->id],
        ]);

    $group = StudentGroup::where('name', 'مجموعة الاختبار')->firstOrFail();
    expect($group->students()->pluck('students.id')->all())->toEqualCanonicalizing([$this->student1->id, $this->student2->id]);
});

it('creates a whatsapp campaign targeting a group', function () {
    $group = StudentGroup::create(['name' => 'مجموعة الحملة']);
    $group->students()->attach([$this->student1->id, $this->student2->id]);

    Livewire::test(ListWhatsappCampaigns::class)
        ->callAction('create', data: [
            'name' => 'حملة تجريبية',
            'student_group_id' => $group->id,
            'message' => 'رسالة تجريبية',
        ]);

    expect(WhatsappCampaign::where('name', 'حملة تجريبية')->exists())->toBeTrue();
});

it('builds recipients from group students, skipping those with no phone', function () {
    $group = StudentGroup::create(['name' => 'مجموعة بناء المستلمين']);
    $group->students()->attach([$this->student1->id, $this->student2->id, $this->student3->id]);

    $campaign = WhatsappCampaign::create([
        'name' => 'حملة بناء',
        'message' => 'مرحباً',
        'student_group_id' => $group->id,
    ]);

    $count = WhatsappCampaignService::buildRecipients($campaign);

    expect($count)->toBe(2)
        ->and($campaign->fresh()->total_count)->toBe(2)
        ->and($campaign->recipients()->pluck('phone')->all())->toEqualCanonicalizing(['972501111111', '972502222222']);
});

it('processes a campaign end to end via the send command, throttled between sends', function () {
    $group = StudentGroup::create(['name' => 'مجموعة الإرسال']);
    $group->students()->attach([$this->student1->id, $this->student2->id]);

    $campaign = WhatsappCampaign::create([
        'name' => 'حملة إرسال',
        'message' => 'رسالة الاختبار',
        'student_group_id' => $group->id,
    ]);

    WhatsappCampaignService::buildRecipients($campaign);

    $this->artisan('whatsapp:campaign:send', ['campaign' => $campaign->id])
        ->assertExitCode(0);

    $campaign->refresh();
    expect($campaign->status)->toBe(WhatsappCampaign::STATUS_COMPLETED)
        ->and($campaign->sent_count + $campaign->failed_count)->toBe(2)
        ->and($campaign->recipients()->where('status', WhatsappCampaignRecipient::STATUS_PENDING)->count())->toBe(0);
});

it('flips the campaign status to running immediately when launched, before the background process runs', function () {
    $group = StudentGroup::create(['name' => 'مجموعة تشغيل فوري']);
    $group->students()->attach([$this->student1->id]);

    $campaign = WhatsappCampaign::create([
        'name' => 'حملة تشغيل فوري',
        'message' => 'رسالة',
        'student_group_id' => $group->id,
    ]);

    Livewire::test(ListWhatsappCampaigns::class)
        ->callTableAction('launch', record: $campaign);

    expect($campaign->fresh()->status)->toBe(WhatsappCampaign::STATUS_RUNNING)
        ->and($campaign->fresh()->started_at)->not->toBeNull();
});
