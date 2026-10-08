<ul class="account-tree">
    @foreach($nodes as $node)
        <li class="account-node {{ $node['receives'] ? 'is-account' : 'is-group' }} {{ $node['id'] && ! $node['is_active'] ? 'is-inactive' : '' }}"
            data-id="{{ $node['id'] }}"
            data-code="{{ $node['code'] }}"
            data-name="{{ $node['name'] }}"
            data-parent="{{ $node['sums_to'] }}"
            data-type="{{ $node['account_type'] }}"
            data-active="{{ $node['is_active'] ? '1' : '0' }}"
            data-receives="{{ $node['receives'] ? '1' : '0' }}"
            data-grouping="{{ ! empty($node['is_grouping']) ? '1' : '0' }}"
            data-balance-nature="{{ $node['balance_nature'] ?? '' }}"
            data-nature="{{ $node['nature'] }}"
            data-balance="{{ $node['balance'] }}">
            <div class="account-row">
                @if(count($node['children']))
                    <button type="button" class="account-toggle" aria-expanded="false" aria-label="Abrir {{ $node['name'] }}">
                        <i class="la la-angle-right"></i>
                    </button>
                @else
                    <span class="account-toggle-spacer" aria-hidden="true"></span>
                @endif
                <button type="button" class="account-pick">
                    <span class="account-code">{{ $node['code'] }}</span>
                    <span class="account-name">{{ $node['name'] }}</span>
                    @if(! empty($node['balance_nature']))
                        <span class="account-badge account-badge-nature">{{ $node['nature'] }}</span>
                    @endif
                    @if($node['id'] && ! $node['is_active'])
                        <span class="account-badge">Inactiva</span>
                    @endif
                </button>
            </div>
            @if(count($node['children']))
                <div class="account-children" hidden>
                    @include('admin.accounting.partials.account_tree', ['nodes' => $node['children']])
                </div>
            @endif
        </li>
    @endforeach
</ul>
