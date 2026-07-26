{{-- Five-rung maturity ladder with the current level highlighted. Pure HTML/CSS.
     Props: $level (int 1-5 or null). --}}
@php
    $current = $level ? (int) $level : null;
    $rungs = [
        1 => 'Data Collection',
        2 => 'Data Reporting',
        3 => 'Data Analysis',
        4 => 'Data-Driven Management',
        5 => 'Learning Health System',
    ];
@endphp
<table style="width:100%;border-collapse:collapse;">
    @foreach (array_reverse($rungs, true) as $num => $name)
        @php $isCurrent = $current === $num; @endphp
        <tr>
            <td style="padding:2px 0;">
                <table style="width:100%;border-collapse:collapse;background:{{ $isCurrent ? '#0369A1' : '#F1F5F9' }};"><tr>
                    <td style="width:26px;text-align:center;padding:5px 0;font-size:11px;font-weight:bold;color:{{ $isCurrent ? '#FFFFFF' : '#64748B' }};">{{ $num }}</td>
                    <td style="padding:5px 6px;font-size:11px;font-weight:{{ $isCurrent ? 'bold' : 'normal' }};color:{{ $isCurrent ? '#FFFFFF' : '#64748B' }};">
                        {{ $name }}{{ $isCurrent ? '  — you are here' : '' }}
                    </td>
                </tr></table>
            </td>
        </tr>
    @endforeach
</table>
