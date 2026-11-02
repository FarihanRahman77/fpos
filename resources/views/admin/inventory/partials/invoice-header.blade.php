{{-- Invoice header: logo + company on the left, document title on the right.
     @include with: title, number, state (optional [label, color, soft color]) --}}
@php
    $ig = fn ($key) => data_get($generalSetting ?? null, $key);
    $logoFile = $ig('logo') ? public_path($ig('logo')) : null;
    $logoSrc = null;
    if ($logoFile && is_file($logoFile)) {
        $logoSrc = 'data:' . (mime_content_type($logoFile) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoFile));
    }
    $companyName = $ig('company_name') ?: config('app.name');
    $cityLine = trim(implode(', ', array_filter([$ig('city'), $ig('state'), $ig('postal_code')])), ', ');
@endphp
<table>
    <tr>
        <td style="width: 55%;">
            @if ($logoSrc)
                <img src="{{ $logoSrc }}" class="inv-logo" alt="">
            @else
                <div class="inv-logo-text">{{ $companyName }}</div>
            @endif
        </td>
        <td style="width: 45%;">
            <div class="inv-title">{{ $title }}</div>
            @if (!empty($number))<div class="inv-no">{{ $number }}</div>@endif
            @if (!empty($state))
                <div class="r"><span class="inv-state" style="color: {{ $state[1] }}; border-color: {{ $state[1] }}; background: {{ $state[2] }};">{{ $state[0] }}</span></div>
            @endif
        </td>
    </tr>
</table>

<table class="inv-company">
    <tr>
        <td style="width: 33%;">
            <div class="name">{{ $companyName }}</div>
            @if ($ig('address'))<div class="line">{{ $ig('address') }}</div>@endif
            @if ($cityLine)<div class="line">{{ $cityLine }}</div>@endif
        </td>
        <td style="width: 67%;">
            <table class="inv-contact">
                @if ($ig('phone'))<tr><td class="k">Phone #</td><td>{{ $ig('phone') }}{{ $ig('phone_two') ? ', ' . $ig('phone_two') : '' }}</td></tr>@endif
                @if ($ig('email'))<tr><td class="k">Email</td><td>{{ $ig('email') }}</td></tr>@endif
                @if ($ig('website'))<tr><td class="k">Website</td><td>{{ $ig('website') }}</td></tr>@endif
                @if ($ig('bin'))<tr><td class="k">BIN</td><td>{{ $ig('bin') }}</td></tr>@endif
            </table>
        </td>
    </tr>
</table>
