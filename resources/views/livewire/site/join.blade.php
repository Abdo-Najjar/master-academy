<?php

use App\Models\Branch;
use App\Models\JoinApplication;
use App\Models\Program;
use App\Support\AppBranding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site')] class extends Component
{
    public string $full_name = '';

    public string $phone = '';

    public ?int $age = null;

    public string $gender = '';

    /** Either a Program id, or `other` when the visitor types their own. */
    #[Url(as: 'program', except: '')]
    public string $program = '';

    public string $program_name = '';

    public ?int $branch_id = null;

    public string $contact_preference = 'whatsapp';

    public string $notes = '';

    /** Honeypot — real visitors never see this field, bots fill it in. */
    public string $website = '';

    public ?string $reference = null;

    public ?string $formError = null;

    public function mount(): void
    {
        // A program id arriving from a program card must still exist and be live.
        if ($this->program !== '' && $this->program !== 'other' && ! $this->programs->has((int) $this->program)) {
            $this->program = '';
        }
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|min:3|max:120',
            'phone' => 'required|string|min:7|max:30',
            'age' => 'required|integer|min:6|max:90',
            'gender' => 'required|in:male,female',
            'program' => 'required|string',
            'program_name' => $this->program === 'other' ? 'required|string|max:120' : 'nullable|string|max:120',
            'branch_id' => 'required|exists:branches,id',
            'contact_preference' => 'required|in:whatsapp,phone',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /** @return array<string, string> */
    public function validationAttributes(): array
    {
        return [
            'full_name' => __('Full name'),
            'phone' => __('Contact / WhatsApp number'),
            'age' => __('Age'),
            'gender' => __('Gender'),
            'program' => __('Requested program'),
            'program_name' => __('Requested program'),
            'branch_id' => __('Suitable branch'),
            'contact_preference' => __('Preferred contact method'),
            'notes' => __('Notes or questions (optional)'),
        ];
    }

    /** @return Collection<int, Program> */
    #[Computed]
    public function programs(): Collection
    {
        return Program::visible()->get()->keyBy('id');
    }

    /** @return Collection<int, Branch> */
    #[Computed]
    public function branches(): Collection
    {
        return Branch::onSite()->get();
    }

    public function submit(): void
    {
        $this->formError = null;

        if (filled($this->website)) {
            // Silently accept so the bot has nothing to learn from the response.
            $this->reference = strtoupper(Str::random(8));

            return;
        }

        $key = 'join-application:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->formError = __('Too many attempts. Please try again in a few minutes.');

            return;
        }

        RateLimiter::hit($key, decaySeconds: 600);

        $this->validate();

        $application = JoinApplication::create([
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'age' => $this->age,
            'gender' => $this->gender,
            'program_id' => $this->program === 'other' ? null : (int) $this->program,
            'program_name' => $this->program === 'other' ? $this->program_name : null,
            'branch_id' => $this->branch_id,
            'contact_preference' => $this->contact_preference,
            'notes' => $this->notes,
        ]);

        $this->reference = $application->reference;
    }

    public function submitAnother(): void
    {
        $this->reset();
        $this->resetValidation();
    }
}; ?>

<main class="join-page">
    @if ($reference)
        <section class="join-shell success-shell">
            <a class="join-brand" href="{{ route('site.landing') }}" wire:navigate aria-label="{{ __('Back to the website') }}">
                <img src="{{ AppBranding::siteLogoUrl('color') }}" alt="{{ AppBranding::appName() }}">
            </a>
            <div class="success-mark" aria-hidden="true">✓</div>
            <p class="join-kicker">{{ __('We received your request') }}</p>
            <h1>{{ __('Welcome to') }}<br>{{ AppBranding::appName() }}</h1>
            <p class="success-copy">
                {{ __('Your join request was registered successfully. Our team will contact you soon to confirm the details, schedule and branch.') }}
            </p>
            <p class="request-number">{{ __('Request number:') }} <b>{{ $reference }}</b></p>
            <div class="success-actions">
                <a class="join-submit" href="{{ route('site.landing') }}" wire:navigate>{{ __('Back to the website') }}</a>
                <button type="button" wire:click="submitAnother">{{ __('Send another request') }}</button>
            </div>
        </section>
    @else
        <section class="join-shell">
            <header class="join-header">
                <a class="join-brand" href="{{ route('site.landing') }}" wire:navigate aria-label="{{ __('Back to the website') }}">
                    <img src="{{ AppBranding::siteLogoUrl('color') }}" alt="{{ AppBranding::appName() }}">
                </a>
                <a class="back-link" href="{{ route('site.landing') }}" wire:navigate>{{ __('Back to the website') }} ←</a>
            </header>

            <div class="join-intro">
                <div>
                    <p class="join-kicker">{{ __('Join Form') }}</p>
                    <h1>{{ __('Start your step') }}<br><em>{{ __('toward a real skill.') }}</em></h1>
                </div>
                <p>{{ __('Fill in the details below and the Master Academy team will contact you to confirm the program, branch and suitable schedule.') }}</p>
            </div>

            <form class="join-form" wire:submit="submit">
                <div class="form-section">
                    <div class="form-section-title">
                        <span>01</span>
                        <div>
                            <h2>{{ __('Trainee details') }}</h2>
                            <p>{{ __('The basic information we need to reach you') }}</p>
                        </div>
                    </div>

                    <div class="join-fields two-columns">
                        <label>
                            <span>{{ __('Full name') }} *</span>
                            <input wire:model="full_name" autocomplete="name" maxlength="120" placeholder="{{ __('Write your full name') }}">
                            @error('full_name') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label>
                            <span>{{ __('Contact / WhatsApp number') }} *</span>
                            <input wire:model="phone" type="tel" inputmode="tel" dir="ltr" autocomplete="tel" maxlength="30" placeholder="059 000 0000">
                            @error('phone') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label>
                            <span>{{ __('Age') }} *</span>
                            <input wire:model="age" type="number" inputmode="numeric" min="6" max="90" placeholder="{{ __('Example: 22') }}">
                            @error('age') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label>
                            <span>{{ __('Gender') }} *</span>
                            <select wire:model="gender">
                                <option value="">{{ __('Choose') }}</option>
                                <option value="male">{{ __('Male') }}</option>
                                <option value="female">{{ __('Female') }}</option>
                            </select>
                            @error('gender') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">
                        <span>02</span>
                        <div>
                            <h2>{{ __('Program selection') }}</h2>
                            <p>{{ __('Pick the path and branch that suit you') }}</p>
                        </div>
                    </div>

                    <div class="join-fields two-columns">
                        <label>
                            <span>{{ __('Requested program') }} *</span>
                            <select wire:model.live="program">
                                <option value="">{{ __('Choose the program') }}</option>
                                @foreach ($this->programs as $item)
                                    <option value="{{ $item->id }}">{{ $item->title }}</option>
                                @endforeach
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                            @error('program') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label>
                            <span>{{ __('Suitable branch') }} *</span>
                            <select wire:model="branch_id">
                                <option value="">{{ __('Choose the branch') }}</option>
                                @foreach ($this->branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <span class="field-error">{{ $message }}</span> @enderror
                        </label>

                        @if ($program === 'other')
                            <label class="wide-field">
                                <span>{{ __('Which program are you looking for?') }} *</span>
                                <input wire:model="program_name" maxlength="120" placeholder="{{ __('Write the program name') }}">
                                @error('program_name') <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                        @endif

                        <label>
                            <span>{{ __('Preferred contact method') }} *</span>
                            <select wire:model="contact_preference">
                                <option value="whatsapp">{{ __('WhatsApp') }}</option>
                                <option value="phone">{{ __('Phone Call') }}</option>
                            </select>
                            @error('contact_preference') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                        <label class="wide-field">
                            <span>{{ __('Notes or questions (optional)') }}</span>
                            <textarea wire:model="notes" rows="4" maxlength="1000" placeholder="{{ __('Write any details you would like the registration team to know') }}"></textarea>
                            @error('notes') <span class="field-error">{{ $message }}</span> @enderror
                        </label>
                    </div>
                </div>

                <input class="website-field" wire:model="website" tabindex="-1" autocomplete="off" aria-hidden="true">

                @if ($formError)
                    <p class="join-error" role="alert">{{ $formError }}</p>
                @endif

                <div class="join-footer">
                    <p><b>{{ __('Note:') }}</b> {{ __('Submitting this form requires no fees, and registration is confirmed only after we contact you.') }}</p>
                    <button class="join-submit" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submit">{{ __('Send join request') }}</span>
                        <span wire:loading wire:target="submit">{{ __('Sending your request...') }}</span>
                        <span aria-hidden="true">←</span>
                    </button>
                </div>
            </form>
        </section>
    @endif
</main>
