@foreach($cards as $card)
    @php
        $params = array_filter([
            'entries' => $card['entries'] === [] ? null : implode(',', $card['entries']),
            'orders' => $card['orders'] === [] ? null : implode(',', $card['orders']),
            'missing' => $card['missing'] ? 1 : null,
        ], fn ($value) => $value !== null && $value !== '');
        $detailUrl = backpack_url('accounting-reconciliation/entries').($params === [] ? '' : '?'.http_build_query($params));
    @endphp
    <div class="reconciliation-alert">
        <div class="process-item-card" data-reconciliation-card data-url="{{ $detailUrl }}" data-title="{{ $card['menu'] }}" data-summary="{{ collect($card['blocks'])->map(fn ($block) => implode(' · ', $block['parts'] ?? [$block['text']]))->implode(' · ') }}" tabindex="0" role="button" aria-haspopup="dialog" aria-controls="reconciliationEntriesModal" style="border-left: 3px solid {{ $card['border'] }};">
            <div class="process-item-title">{{ $card['menu'] }}</div>
            @foreach($card['blocks'] as $block)
                <div class="process-item-meta">
                    @foreach($block['parts'] ?? [$block['text']] as $part)
                        <span @if(!empty($block['color'])) style="color: {{ $block['color'] }}; font-weight: bold;" @endif>
                            @if(!empty($block['icon']) && $loop->first)<i class="la {{ $block['icon'] }}"></i> @endif{{ $part }}
                        </span>
                    @endforeach
                </div>
            @endforeach
            <div class="process-item-meta">
                <span style="font-weight: bold;">Ver asientos</span>
            </div>
        </div>
    </div>
@endforeach
