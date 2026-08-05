<?php

use App\Models\Branch;
use App\Models\Program;
use App\Models\SiteMedia;
use App\Models\Testimonial;
use App\Models\Trainer;
use App\Settings\SiteSettings;
use App\Support\AppBranding;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site')] class extends Component
{
    /** Active program category filter, or `all`. */
    #[Url(except: 'all')]
    public string $category = 'all';

    public function filterBy(string $category): void
    {
        $this->category = $category;
    }

    public function settings(): SiteSettings
    {
        return app(SiteSettings::class);
    }

    /** @return Collection<int, Program> */
    #[Computed]
    public function programs(): Collection
    {
        return Program::visible()
            ->when($this->category !== 'all', fn ($query) => $query->where('category', $this->category))
            ->get();
    }

    /**
     * Only the categories that actually have a visible program, so the filter
     * bar never offers a choice that returns nothing.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function categories(): array
    {
        $used = Program::visible()->distinct()->pluck('category')->all();

        return ['all' => __('All')] + array_filter(
            Program::categoryOptions(),
            fn (string $key): bool => in_array($key, $used, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /** @return Collection<int, Trainer> */
    #[Computed]
    public function trainers(): Collection
    {
        return Trainer::onSite()->with('media')->get();
    }

    /** @return Collection<int, Testimonial> */
    #[Computed]
    public function testimonials(): Collection
    {
        return Testimonial::visible()->with(['media', 'student.media'])->get();
    }

    /** @return Collection<int, SiteMedia> */
    #[Computed]
    public function gallery(): Collection
    {
        return SiteMedia::visible()->with('media')->get();
    }

    /** @return Collection<int, Branch> */
    #[Computed]
    public function branches(): Collection
    {
        return Branch::onSite()->get();
    }
}; ?>

@php
    $settings = $this->settings();
@endphp

<div>
    <header class="site-header" id="top">
        <div class="container header-inner">
            <a class="brand" href="#top" aria-label="{{ __('Home') }}">
                <img src="{{ AppBranding::siteLogoUrl('color') }}" width="496" height="133" alt="{{ AppBranding::appName() }}">
            </a>

            <nav class="primary-nav" id="primary-nav" aria-label="{{ __('Main navigation') }}">
                <a href="#top">{{ __('Home') }}</a>
                <a href="#about">{{ __('About Us') }}</a>
                <a href="#programs">{{ __('Programs') }}</a>
                <a class="login-button" href="{{ route('portal') }}" wire:navigate>{{ __('Login') }}</a>
            </nav>

            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
                <span></span><span></span><span></span>
                <span class="sr-only">{{ __('Open menu') }}</span>
            </button>
        </div>
    </header>

    <main id="main">
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-glow" aria-hidden="true"></div>
            <div class="container hero-grid">
                <div class="hero-copy reveal">
                    <p class="eyebrow"><span></span> {{ $settings->hero_eyebrow }}</p>
                    <h1 id="hero-title">{{ $settings->hero_title }}<br><em>{{ $settings->hero_title_highlight }}</em></h1>
                    <p class="hero-lead">{{ $settings->hero_lead }}</p>
                    <div class="hero-actions">
                        <a class="button button-primary" href="#programs">{{ __('Explore Programs') }} <span aria-hidden="true">←</span></a>
                        <a class="text-link" href="{{ route('site.join') }}" wire:navigate>{{ __('Join Form') }}</a>
                    </div>
                    <div class="accreditation-note">
                        <span class="seal" aria-hidden="true">✓</span>
                        <div>
                            <strong>{{ $settings->hero_badge_title }}</strong>
                            <small>{{ $settings->hero_badge_note }}</small>
                        </div>
                    </div>
                </div>

                <div class="hero-visual reveal">
                    <div class="hero-ring" aria-hidden="true"></div>
                    <img
                        class="hero-people"
                        src="{{ asset('site/assets/hero-trainees.png') }}"
                        width="1023"
                        height="1537"
                        alt="{{ __('Trainees across various professional and technical fields') }}"
                        fetchpriority="high"
                    >
                </div>
            </div>
        </section>

        @if ($this->gallery->isNotEmpty())
            <section class="gallery gallery-home section" id="gallery" aria-labelledby="gallery-title">
                <div class="container">
                    <div class="section-heading reveal">
                        <div>
                            <p class="section-kicker">{{ __('From the heart of training') }}</p>
                            <h2 id="gallery-title">{{ __('See the experience') }}<br>{{ __('from the first moment') }}</h2>
                        </div>
                        <p>{{ __('Snapshots from practical sessions, projects and activities inside our branches.') }}</p>
                    </div>
                    <div class="gallery-grid">
                        @foreach ($this->gallery as $item)
                            @php $assetUrl = $item->asset_url; @endphp
                            <button
                                class="gallery-item reveal visible"
                                type="button"
                                @disabled(! $assetUrl)
                                @if ($assetUrl)
                                    data-media-url="{{ $assetUrl }}"
                                    data-media-type="{{ $item->type }}"
                                    data-media-title="{{ $item->title }}"
                                @endif
                            >
                                @if (! $assetUrl)
                                    <span class="gallery-placeholder" aria-hidden="true">{{ $item->type === 'video' ? '▶' : '✦' }}</span>
                                @elseif ($item->type === 'video')
                                    <video src="{{ $assetUrl }}" preload="metadata" muted playsinline></video>
                                @else
                                    <img src="{{ $assetUrl }}" alt="{{ $item->title }}" loading="lazy">
                                @endif
                                <span class="gallery-caption">
                                    <span>{{ $item->title }}</span>
                                    @if ($item->type === 'video')
                                        <span class="play-icon" aria-hidden="true">▶</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if (filled($settings->stats))
            <section class="impact" aria-label="{{ __('Master Academy impact') }}">
                <div class="container impact-grid">
                    @foreach ($settings->stats as $stat)
                        <article class="impact-card reveal">
                            <strong>{{ $stat['value'] }}</strong>
                            <p>{{ $stat['label'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="director section" aria-labelledby="director-title">
            <div class="container director-card reveal">
                <div class="director-photo-wrap">
                    <img
                        class="director-photo"
                        src="{{ asset('site/assets/mohammed-alassar-director.jpg') }}"
                        width="916"
                        height="931"
                        alt="{{ $settings->director_name }}"
                    >
                    <span class="director-badge">{{ __('Director’s Message') }}</span>
                </div>
                <div class="director-copy">
                    <p class="section-kicker">{{ __('Our vision starts with people') }}</p>
                    <h2 id="director-title">{{ __('We turn learning') }}<br>{{ __('into a real opportunity') }}</h2>
                    <blockquote>«{{ $settings->director_quote }}»</blockquote>
                    <div class="director-signature">
                        <strong>{{ $settings->director_name }}</strong>
                        <span>{{ $settings->director_role }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="about section" id="about" aria-labelledby="about-title">
            <div class="container about-grid">
                <div class="about-copy reveal">
                    <p class="section-kicker">{{ __('About Us') }}</p>
                    <h2 id="about-title">{{ __('Training closer') }}<br>{{ __('to real work') }}</h2>
                </div>
                <div class="about-body reveal">
                    <p>{{ $settings->about_text }}</p>
                    <div class="value-list">
                        @foreach ($settings->about_values as $value)
                            <span>{{ $value }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        @if ($this->trainers->isNotEmpty())
            <section class="staff section" id="staff" aria-labelledby="staff-title">
                <div class="container">
                    <div class="section-heading reveal">
                        <div>
                            <p class="section-kicker">{{ __('Academic Staff') }}</p>
                            <h2 id="staff-title">{{ __('Experience that trains you.') }}<br>{{ __('Follow-up that grows you.') }}</h2>
                        </div>
                        <p>{{ __('Specialized trainers who combine practical experience with the ability to turn knowledge into application.') }}</p>
                    </div>
                    <div class="staff-grid">
                        @foreach ($this->trainers as $trainer)
                            <article class="staff-card reveal visible">
                                <div class="staff-photo">
                                    @if ($photo = $trainer->getFirstMediaUrl('main'))
                                        <img src="{{ $photo }}" alt="{{ $trainer->name }}" loading="lazy">
                                    @endif
                                    <span>{{ $trainer->specialty }}</span>
                                </div>
                                <div class="staff-card-body">
                                    <h3>{{ $trainer->name }}</h3>
                                    <p>{{ $trainer->bio }}</p>
                                    @if (filled($trainer->student_opinion))
                                        <blockquote>
                                            <span aria-hidden="true">“</span>
                                            {{ $trainer->student_opinion }}
                                        </blockquote>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="programs section" id="programs" aria-labelledby="programs-title">
            <div class="container">
                <div class="section-heading reveal">
                    <div>
                        <p class="section-kicker">{{ __('Choose your path') }}</p>
                        <h2 id="programs-title">{{ __('Programs that build') }}<br>{{ __('real skill') }}</h2>
                    </div>
                    <p>{{ __('Professional, technical and creative programs, updated continuously to match market opportunities.') }}</p>
                </div>

                <div class="filters reveal" role="group" aria-label="{{ __('Filter programs') }}">
                    @foreach ($this->categories as $key => $label)
                        <button
                            class="filter-button @if ($key === $category) active @endif"
                            type="button"
                            wire:click="filterBy('{{ $key }}')"
                            aria-pressed="{{ $key === $category ? 'true' : 'false' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <div class="program-grid" aria-live="polite">
                    @forelse ($this->programs as $index => $program)
                        <article
                            class="program-card reveal visible @if ($program->is_featured) featured @endif"
                            @if ($program->is_featured) data-featured-label="{{ __('Most requested') }}" @endif
                            wire:key="program-{{ $program->id }}"
                        >
                            <div class="program-image">
                                <span class="program-image-fallback" aria-hidden="true">{{ $program->icon }}</span>
                                @if ($cover = $program->cover_url)
                                    <img src="{{ $cover }}" alt="{{ $program->title }}" loading="lazy">
                                @endif
                                <span class="program-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <div class="program-body">
                                <div class="card-top">
                                    <span class="program-icon" aria-hidden="true">{{ $program->icon }}</span>
                                    <span class="program-category">{{ $program->category_label }}</span>
                                </div>
                                <h3>{{ $program->title }}</h3>
                                <p>{{ $program->description }}</p>
                                <div class="program-meta">
                                    @foreach ([$program->duration, $program->price, $program->branches_label] as $meta)
                                        @if (filled($meta))
                                            <span>{{ $meta }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                @if (filled($program->registration_url))
                                    <a class="card-link" href="{{ $program->registration_url }}" target="_blank" rel="noopener">
                                        <span>{{ __('Go to registration') }}</span>
                                        <span aria-hidden="true">←</span>
                                    </a>
                                @else
                                    <a class="card-link" href="{{ route('site.join', ['program' => $program->id]) }}" wire:navigate>
                                        <span>{{ __('Register for this program') }}</span>
                                        <span aria-hidden="true">←</span>
                                    </a>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">{{ __('Programs will be added soon.') }}</div>
                    @endforelse
                </div>
            </div>
        </section>

        @if ($this->testimonials->isNotEmpty())
            <section class="testimonials section" id="testimonials" aria-labelledby="testimonials-title">
                <div class="container">
                    <div class="section-heading reveal">
                        <div>
                            <p class="section-kicker">{{ __('What they said about us') }}</p>
                            <h2 id="testimonials-title">{{ __('Impact that speaks') }}<br>{{ __('for itself') }}</h2>
                        </div>
                        <p>{{ __('Real experiences from trainees who took new steps after our programs.') }}</p>
                    </div>
                    <div class="testimonial-grid">
                        @foreach ($this->testimonials as $testimonial)
                            <article class="testimonial-card reveal visible">
                                <span class="quote-mark" aria-hidden="true">“</span>
                                <blockquote>{{ $testimonial->quote }}</blockquote>
                                <div class="person">
                                    <span class="person-avatar">
                                        @if ($avatar = $testimonial->avatar_url)
                                            <img src="{{ $avatar }}" alt="{{ $testimonial->name }}" loading="lazy">
                                        @else
                                            {{ mb_substr($testimonial->name, 0, 1) }}
                                        @endif
                                    </span>
                                    <span class="person-info">
                                        <strong>{{ $testimonial->name }}</strong>
                                        <span>{{ $testimonial->role }}</span>
                                    </span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if ($this->branches->isNotEmpty())
            <section class="branches section" id="branches" aria-labelledby="branches-title">
                <div class="container branches-inner reveal">
                    <div>
                        <p class="section-kicker">{{ __('We are closer to you') }}</p>
                        <h2 id="branches-title">{{ __('Start from') }}<br>{{ __('your nearest branch') }}</h2>
                    </div>
                    <div class="branch-list">
                        @foreach ($this->branches as $index => $branch)
                            <article class="branch-item">
                                <span class="branch-index">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <div>
                                    <h3>{{ $branch->name }}</h3>
                                    <p>{{ $branch->address }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <section class="contact section" id="contact" aria-labelledby="contact-title">
            <div class="container">
                <div class="contact-card reveal">
                    <div class="contact-copy">
                        <p class="section-kicker">{{ __('Contact Us') }}</p>
                        <h2 id="contact-title">{{ __('Ready to take') }}<br>{{ __('your next step?') }}</h2>
                        <p>{{ __('Our team is ready to help you choose the program and branch that suit you best.') }}</p>
                        <div class="contact-actions">
                            <a class="button button-contact" href="{{ route('site.join') }}" wire:navigate>
                                {{ __('Join Form') }} <span aria-hidden="true">←</span>
                            </a>
                            @if (filled($settings->contact_whatsapp))
                                <a class="button button-contact" href="https://wa.me/{{ $settings->contact_whatsapp }}" target="_blank" rel="noopener">
                                    {{ __('Chat on WhatsApp') }} <span aria-hidden="true">←</span>
                                </a>
                            @endif
                            @if (filled($settings->contact_phone))
                                <a class="contact-phone" href="tel:{{ $settings->contact_phone }}" dir="ltr">{{ $settings->contact_phone }}</a>
                            @endif
                        </div>
                    </div>
                    <div class="contact-details">
                        @foreach ($this->branches as $index => $branch)
                            <div class="contact-detail">
                                <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <div>
                                    <strong>{{ $branch->name }}</strong>
                                    <small>{{ $branch->address }}</small>
                                </div>
                            </div>
                        @endforeach
                        @if (filled($settings->license_number))
                            <p class="license-note">
                                <b>{{ __('Accredited training center') }}</b> · {{ __('License No.') }} {{ $settings->license_number }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <img src="{{ AppBranding::siteLogoUrl('white') }}" width="497" height="134" alt="{{ AppBranding::appName() }}">
            <p>© {{ now()->year }} {{ AppBranding::appName() }}. {{ __('All rights reserved.') }}</p>
            <div class="footer-links">
                <a href="{{ route('site.join') }}" wire:navigate>{{ __('Join Form') }}</a>
                <a href="{{ route('portal') }}" wire:navigate>{{ __('Login portal') }} ←</a>
            </div>
        </div>
    </footer>

    <dialog class="media-dialog" id="media-dialog">
        <button type="button" class="dialog-close" aria-label="{{ __('Close') }}">×</button>
        <div class="dialog-body"></div>
    </dialog>
</div>
