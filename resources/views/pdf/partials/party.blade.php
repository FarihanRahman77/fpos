{{-- Party / details blocks side by side.
     @include('pdf.partials.party', ['parties' => [
         ['label' => 'Bill to', 'name' => 'Customer', 'lines' => ['Phone: ...', 'Address ...']],
         ['label' => 'Details', 'kv' => ['Invoice #' => '...', 'Date' => '...']],
     ]]) --}}
@php $parties = array_values(array_filter($parties ?? [])); $w = $parties ? floor(100 / count($parties)) : 100; @endphp
@if ($parties)
    <table class="pdf-party">
        <tr>
            @foreach ($parties as $p)
                <td class="pdf-party-cell {{ $loop->first ? 'first' : '' }} {{ $loop->last ? 'last' : '' }}" style="width: {{ $w }}%;">
                    <div class="pdf-party-in">
                        <div class="pdf-label">{{ $p['label'] ?? '' }}</div>
                        @if (!empty($p['name']))<div class="pdf-party-name">{{ $p['name'] }}</div>@endif
                        @foreach (array_filter($p['lines'] ?? [], fn ($l) => $l !== null && $l !== '') as $line)
                            <div class="pdf-party-line">{{ $line }}</div>
                        @endforeach
                        @if (!empty($p['kv']))
                            <table class="pdf-kv-mini">
                                @foreach ($p['kv'] as $k => $v)
                                    @if ($v !== null && $v !== '')<tr><td class="k">{{ $k }}</td><td>{{ $v }}</td></tr>@endif
                                @endforeach
                            </table>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    </table>
@endif
