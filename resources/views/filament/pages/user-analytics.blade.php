<x-filament-panels::page>
    @php
        $analytics = $this->analytics;
        $options = $this->filterOptions;
        $largest = fn (array $items) => max(1, ...array_column($items, 'count'));
        $coverage = fn (int $value) => $analytics['summary']['total'] ? round($value / $analytics['summary']['total'] * 100, 1) : 0;
    @endphp

    <div class="su-analytics">
        <section class="su-analytics-hero">
            <div>
                <span>PLATFORM INTELLIGENCE</span>
                <h2>Understand who is joining SportsUniverse.</h2>
                <p>Explore real registration, profile, location, sport and device data. Every card and chart responds to the filters below.</p>
            </div>
            <button type="button" wire:click="resetFilters">Reset all filters</button>
        </section>

        <section class="su-filter-panel">
            <label>Registration period<select wire:model.live="period"><option value="0">All time</option><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option><option value="365">Last 12 months</option></select></label>
            <label>Registration channel<select wire:model.live="source"><option value="">All channels</option><option value="mobile">Mobile app</option><option value="web">Web</option><option value="unknown">Unknown</option></select></label>
            <label>Role<select wire:model.live="role"><option value="">All roles</option>@foreach($options['roles'] as $item)<option value="{{ $item }}">{{ str($item)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
            <label>Province<select wire:model.live="province"><option value="">All provinces</option>@foreach($options['provinces'] as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></label>
            <label>City<select wire:model.live="city"><option value="">All cities</option>@foreach($options['cities'] as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></label>
            <label>Sport<select wire:model.live="sport"><option value="">All sports</option>@foreach($options['sports'] as $item)<option value="{{ $item['id'] }}">{{ $item['name'] }}</option>@endforeach</select></label>
            <label>Gender<select wire:model.live="gender"><option value="">All genders</option><option value="female">Female</option><option value="male">Male</option><option value="non_binary">Non-binary</option><option value="prefer_not_to_say">Prefer not to say</option></select></label>
            <label>Status<select wire:model.live="status"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="disabled">Disabled</option></select></label>
            <label>Minimum age<input wire:model.live.debounce.400ms="ageMin" type="number" min="0" max="100" placeholder="Any"></label>
            <label>Maximum age<input wire:model.live.debounce.400ms="ageMax" type="number" min="0" max="100" placeholder="Any"></label>
        </section>

        <section class="su-metric-grid" wire:loading.class="is-loading">
            <article><i class="metric-icon blue"><x-filament::icon icon="heroicon-o-users"/></i><small>FILTERED USERS</small><strong>{{ number_format($analytics['summary']['total']) }}</strong><p>Accounts matching the current filter set.</p></article>
            <article><i class="metric-icon green"><x-filament::icon icon="heroicon-o-check-badge"/></i><small>ACTIVE ACCOUNTS</small><strong>{{ number_format($analytics['summary']['active']) }}</strong><p>{{ $coverage($analytics['summary']['active']) }}% of filtered accounts can access the platform.</p></article>
            <article><i class="metric-icon cyan"><x-filament::icon icon="heroicon-o-device-phone-mobile"/></i><small>MOBILE REGISTRATIONS</small><strong>{{ number_format($analytics['summary']['mobile']) }}</strong><p>{{ $analytics['summary']['mobile_share'] }}% of registrations with a known channel.</p></article>
            <article><i class="metric-icon violet"><x-filament::icon icon="heroicon-o-computer-desktop"/></i><small>WEB REGISTRATIONS</small><strong>{{ number_format($analytics['summary']['web']) }}</strong><p>{{ number_format($analytics['summary']['unknown_source']) }} historical accounts have no source attribution.</p></article>
            <article><i class="metric-icon pink"><x-filament::icon icon="heroicon-o-envelope-open"/></i><small>VERIFIED EMAILS</small><strong>{{ number_format($analytics['summary']['verified']) }}</strong><p>{{ $coverage($analytics['summary']['verified']) }}% verification coverage.</p></article>
            <article><i class="metric-icon orange"><x-filament::icon icon="heroicon-o-identification"/></i><small>COMPLETE PROFILES</small><strong>{{ number_format($analytics['summary']['complete_profiles']) }}</strong><p>{{ $coverage($analytics['summary']['complete_profiles']) }}% have at least 80% profile completeness.</p></article>
        </section>

        <section class="su-chart wide">
            <header><div><small>REGISTRATION MOMENTUM</small><h3>New accounts over time</h3></div><span>{{ count($analytics['trend']) }} reporting months</span></header>
            <div class="trend-bars">
                @forelse($analytics['trend'] as $item)
                    <div title="{{ $item['label'] }}: {{ number_format($item['count']) }} registrations"><b>{{ number_format($item['count']) }}</b><i style="height: {{ max(4, $item['count'] / $largest($analytics['trend']) * 100) }}%"></i><span>{{ $item['label'] }}</span></div>
                @empty <p class="empty-chart">No registrations match these filters.</p> @endforelse
            </div>
        </section>

        <div class="su-chart-grid">
            @foreach([
                ['title'=>'Registration channel','eyebrow'=>'ACQUISITION','items'=>$analytics['sources']],
                ['title'=>'Devices used','eyebrow'=>'PLATFORM USAGE','items'=>$analytics['devices']],
                ['title'=>'Operating systems','eyebrow'=>'PLATFORM USAGE','items'=>$analytics['platforms']],
                ['title'=>'Browsers and apps','eyebrow'=>'PLATFORM USAGE','items'=>$analytics['browsers']],
                ['title'=>'Members by role','eyebrow'=>'COMMUNITY MIX','items'=>$analytics['roles']],
                ['title'=>'Top provinces','eyebrow'=>'LOCATION','items'=>$analytics['locations']],
                ['title'=>'Top cities','eyebrow'=>'LOCAL COMMUNITIES','items'=>$analytics['cities']],
                ['title'=>'Popular sports','eyebrow'=>'SPORT INTEREST','items'=>$analytics['sports']],
                ['title'=>'Age groups','eyebrow'=>'DEMOGRAPHICS','items'=>$analytics['ages']],
                ['title'=>'Gender profile','eyebrow'=>'DEMOGRAPHICS','items'=>$analytics['genders']],
                ['title'=>'Account status','eyebrow'=>'ACCOUNT HEALTH','items'=>$analytics['statuses']],
            ] as $chart)
                <section class="su-chart">
                    <header><div><small>{{ $chart['eyebrow'] }}</small><h3>{{ $chart['title'] }}</h3></div></header>
                    <div class="rank-bars">
                        @forelse($chart['items'] as $item)
                            <div><span>{{ $item['label'] ?: 'Unknown' }}</span><i><b style="width: {{ max(2, $item['count'] / $largest($chart['items']) * 100) }}%"></b></i><strong>{{ number_format($item['count']) }}</strong></div>
                        @empty <p class="empty-chart">No data for this selection.</p> @endforelse
                    </div>
                </section>
            @endforeach
        </div>

        <section class="su-data-quality">
            <header><div><small>DATA QUALITY</small><h3>Profile information coverage</h3></div><p>Low coverage means that segment should be interpreted cautiously.</p></header>
            @foreach(['location'=>'Location','age'=>'Date of birth','sport'=>'Primary sport'] as $key=>$label)
                <div><span>{{ $label }}</span><i><b style="width: {{ $coverage($analytics['profile_coverage'][$key]) }}%"></b></i><strong>{{ $coverage($analytics['profile_coverage'][$key]) }}%</strong></div>
            @endforeach
        </section>
    </div>

    <style>
        .su-analytics{display:grid;gap:1.25rem}.su-analytics-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:2rem;padding:2rem;border-radius:1.5rem;color:#fff;background:radial-gradient(circle at 85% 10%,rgba(24,198,232,.25),transparent 32%),linear-gradient(135deg,#0b1930,#203d70 62%,#633c82);box-shadow:0 20px 45px rgba(16,29,49,.18)}.su-analytics-hero span,.su-chart header small,.su-data-quality small{color:#64dff5;font-size:.68rem;font-weight:900;letter-spacing:.12em}.su-analytics-hero h2{max-width:650px;margin:.45rem 0;font-size:clamp(1.7rem,3vw,2.6rem);line-height:1.05}.su-analytics-hero p{max-width:720px;color:#ced9e9}.su-analytics-hero button{flex:none;padding:.7rem 1rem;border:1px solid rgba(255,255,255,.25);border-radius:.8rem;background:rgba(255,255,255,.1);font-weight:800}.su-filter-panel{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:.75rem;padding:1rem;border:1px solid #dfe6ef;border-radius:1.1rem;background:#fff}.su-filter-panel label{display:grid;gap:.3rem;color:#66758a;font-size:.72rem;font-weight:750}.su-filter-panel select,.su-filter-panel input{width:100%;height:2.55rem;padding:0 .7rem;border:1px solid #d8e1ec;border-radius:.65rem;background:#f9fbfd;color:#17243a}.su-metric-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;transition:opacity .15s}.su-metric-grid.is-loading{opacity:.55}.su-metric-grid article{position:relative;display:grid;grid-template-columns:3rem 1fr;min-height:145px;padding:1.15rem;border:1px solid #dfe6ef;border-radius:1.1rem;background:#fff;box-shadow:0 10px 28px rgba(25,42,68,.05)}.metric-icon{grid-row:1/4;display:grid;width:2.5rem;height:2.5rem;place-items:center;border-radius:.75rem}.metric-icon svg{width:1.25rem}.metric-icon.blue{color:#476fea;background:#edf2ff}.metric-icon.green{color:#139467;background:#e8f8f1}.metric-icon.cyan{color:#089dbd;background:#e7f9fc}.metric-icon.violet{color:#7653d7;background:#f1edff}.metric-icon.pink{color:#cf3c8a;background:#fff0f7}.metric-icon.orange{color:#d17820;background:#fff4e7}.su-metric-grid small{color:#778397;font-size:.65rem;font-weight:850;letter-spacing:.08em}.su-metric-grid strong{color:#101d31;font-size:1.8rem;letter-spacing:-.04em}.su-metric-grid p{margin:0;color:#778397;font-size:.75rem;line-height:1.4}.su-chart-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}.su-chart,.su-data-quality{padding:1.25rem;border:1px solid #dfe6ef;border-radius:1.1rem;background:#fff;box-shadow:0 10px 28px rgba(25,42,68,.045)}.su-chart.wide{min-height:310px}.su-chart header,.su-data-quality header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1rem}.su-chart h3,.su-data-quality h3{margin:.2rem 0;color:#17243a;font-size:1.15rem}.su-chart header span,.su-data-quality header p{color:#8793a4;font-size:.72rem}.rank-bars{display:grid;gap:.78rem}.rank-bars>div{display:grid;grid-template-columns:minmax(95px,1fr) 2fr 55px;align-items:center;gap:.6rem;font-size:.76rem}.rank-bars>div>span{overflow:hidden;color:#526178;text-overflow:ellipsis;white-space:nowrap}.rank-bars>div>i,.su-data-quality>div>i{height:.5rem;overflow:hidden;border-radius:99px;background:#edf1f6}.rank-bars>div>i>b,.su-data-quality>div>i>b{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#18bfe0,#5272e8,#d94797)}.rank-bars>div>strong{text-align:right;color:#17243a}.trend-bars{display:flex;height:220px;align-items:flex-end;gap:.55rem;padding-top:1.5rem;overflow-x:auto}.trend-bars>div{display:grid;min-width:54px;height:100%;grid-template-rows:20px 1fr 27px;align-items:end;text-align:center}.trend-bars b{color:#526178;font-size:.62rem}.trend-bars i{display:block;min-height:4px;border-radius:.45rem .45rem .15rem .15rem;background:linear-gradient(180deg,#14c2e2,#5271e8 62%,#d94797)}.trend-bars span{padding-top:.4rem;color:#8591a3;font-size:.58rem;white-space:nowrap}.su-data-quality>div{display:grid;grid-template-columns:130px 1fr 55px;align-items:center;gap:.8rem;margin:.75rem 0;color:#526178;font-size:.78rem}.su-data-quality>div>strong{text-align:right}.empty-chart{padding:2rem;text-align:center;color:#8a96a7}@media(max-width:1100px){.su-filter-panel{grid-template-columns:repeat(3,1fr)}.su-metric-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.su-analytics-hero{align-items:flex-start;flex-direction:column;padding:1.35rem}.su-filter-panel,.su-metric-grid,.su-chart-grid{grid-template-columns:1fr}.su-filter-panel{gap:.6rem}.su-metric-grid article{min-height:125px}.su-data-quality>div{grid-template-columns:95px 1fr 45px}}
    </style>
</x-filament-panels::page>
