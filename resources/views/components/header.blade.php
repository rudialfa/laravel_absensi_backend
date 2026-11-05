@php
    $me = auth()->user();
    // Staff = superadmin/staff tanpa tenant (panel superadmin)
    $isStaff = $me->company_id === null && in_array($me->role, ['superadmin', 'staff'], true);

    // Inbox pribadi (app_notifications milik user yang login)
    $inboxQuery = \App\Models\AppNotification::where('user_id', $me->id);
    $unreadCount = (clone $inboxQuery)->whereNull('read_at')->count();
    $latestInbox = (clone $inboxQuery)->latest('id')->limit(6)->get();

    // Pesan = tiket bantuan yang belum selesai (khusus staff)
    $openTicketQuery = \App\Models\SupportTicket::whereIn('status', ['open', 'in_progress']);
    $openTickets = $isStaff ? (clone $openTicketQuery)->count() : 0;
    $latestTickets = $isStaff
        ? (clone $openTicketQuery)->with('company:id,name')->latest('id')->limit(5)->get()
        : collect();

    $inboxIcons = [
        'chat' => ['fa-comment', 'primary'], 'approval' => ['fa-check', 'success'],
        'reminder' => ['fa-bell', 'warning'], 'attendance' => ['fa-user-clock', 'info'],
        'schedule' => ['fa-calendar-alt', 'info'], 'holiday' => ['fa-umbrella-beach', 'info'],
        'report' => ['fa-chart-bar', 'primary'], 'payroll' => ['fa-money-bill-wave', 'success'],
        'school' => ['fa-school', 'primary'], 'subscription' => ['fa-receipt', 'danger'],
        'system' => ['fa-cog', 'secondary'],
    ];
@endphp

<div class="navbar-bg"></div>
<nav class="navbar navbar-expand-lg main-navbar">
    <ul class="navbar-nav mr-3">
        <li>
            <a href="#" data-toggle="sidebar" class="nav-link nav-link-lg" aria-label="Buka menu">
                <i class="fas fa-bars"></i>
            </a>
        </li>
        <li>
            <a href="#" data-toggle="search" class="nav-link nav-link-lg d-sm-none" aria-label="Cari">
                <i class="fas fa-search"></i>
            </a>
        </li>
    </ul>

    {{-- ================= PENCARIAN GLOBAL ================= --}}
    <form class="form-inline mr-auto" action="{{ route('superadmin.global-search') }}" method="GET" role="search">
        <div class="search-element">
            <input class="form-control" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Cari tenant, user, invoice…" aria-label="Cari" data-width="250" minlength="2">
            <button class="btn" type="submit" aria-label="Cari"><i class="fas fa-search"></i></button>
        </div>
    </form>

    <ul class="navbar-nav navbar-right">

        {{-- ================= PESAN (tiket bantuan) — staff ================= --}}
        @if ($isStaff)
            <li class="dropdown dropdown-list-toggle">
                <a href="#" data-toggle="dropdown" class="nav-link nav-link-lg message-toggle {{ $openTickets ? 'beep' : '' }}" aria-label="Pesan bantuan">
                    <i class="far fa-envelope"></i>
                    @if ($openTickets)
                        <span class="badge badge-danger badge-pill" style="position:absolute;top:6px;right:4px;font-size:10px">{{ $openTickets > 99 ? '99+' : $openTickets }}</span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-list dropdown-menu-right">
                    <div class="dropdown-header">
                        Tiket bantuan belum selesai
                        <div class="float-right">
                            <span class="text-muted">{{ $openTickets }} total</span>
                        </div>
                    </div>
                    <div class="dropdown-list-content dropdown-list-message">
                        @forelse ($latestTickets as $t)
                            <a href="{{ route('superadmin.support-tickets.show', $t->id) }}" class="dropdown-item {{ $t->status === 'open' ? 'dropdown-item-unread' : '' }}">
                                <div class="dropdown-item-avatar">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:35px;height:35px">
                                        <i class="fas fa-life-ring text-primary"></i>
                                    </div>
                                </div>
                                <div class="dropdown-item-desc">
                                    <b>{{ \Illuminate\Support\Str::limit($t->subject, 40) }}</b>
                                    <p>{{ $t->company->name ?? 'Tenant tidak diketahui' }} · {{ $t->status === 'open' ? 'baru' : 'diproses' }}</p>
                                    <div class="time">{{ $t->created_at->diffForHumans() }}</div>
                                </div>
                            </a>
                        @empty
                            <div class="dropdown-item text-center text-muted py-3">Tidak ada tiket yang menunggu.</div>
                        @endforelse
                    </div>
                    <div class="dropdown-footer text-center">
                        <a href="{{ route('superadmin.support-tickets.index') }}">Lihat semua tiket <i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
            </li>
        @endif

        {{-- ================= NOTIFIKASI (inbox pribadi) ================= --}}
        <li class="dropdown dropdown-list-toggle">
            <a href="#" data-toggle="dropdown" class="nav-link notification-toggle nav-link-lg {{ $unreadCount ? 'beep' : '' }}" aria-label="Notifikasi">
                <i class="far fa-bell"></i>
                @if ($unreadCount)
                    <span class="badge badge-danger badge-pill" style="position:absolute;top:6px;right:4px;font-size:10px">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
            </a>
            <div class="dropdown-menu dropdown-list dropdown-menu-right">
                <div class="dropdown-header">
                    Notifikasi
                    <div class="float-right">
                        @if ($unreadCount)
                            <form method="POST" action="{{ route('superadmin.header.inbox.read-all') }}" class="d-inline">
                                @csrf
                                <button class="btn btn-link p-0 m-0 align-baseline" style="font-size:inherit">Tandai semua dibaca</button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="dropdown-list-content dropdown-list-icons">
                    @forelse ($latestInbox as $n)
                        @php [$icon, $color] = $inboxIcons[$n->category] ?? ['fa-bell', 'secondary']; @endphp
                        <form method="POST" action="{{ route('superadmin.header.inbox.read', $n->id) }}" class="m-0">
                            @csrf
                            <button type="submit" class="dropdown-item w-100 text-left {{ $n->read_at ? '' : 'dropdown-item-unread' }}" style="border:0;background:none">
                                <div class="dropdown-item-icon bg-{{ $color }} text-white">
                                    <i class="fas {{ $icon }}"></i>
                                </div>
                                <div class="dropdown-item-desc">
                                    <b>{{ \Illuminate\Support\Str::limit($n->title, 60) }}</b>
                                    <div class="text-muted small">{{ \Illuminate\Support\Str::limit($n->body, 80) }}</div>
                                    <div class="time {{ $n->read_at ? '' : 'text-primary' }}">{{ $n->created_at->diffForHumans() }}</div>
                                </div>
                            </button>
                        </form>
                    @empty
                        <div class="dropdown-item text-center text-muted py-3">Belum ada notifikasi.</div>
                    @endforelse
                </div>
                <div class="dropdown-footer text-center">
                    <a href="{{ route('superadmin.header.inbox') }}">Lihat semua notifikasi <i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        </li>

        {{-- ================= AKUN ================= --}}
        <li class="dropdown">
            <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                <img alt="Foto profil" src="{{ asset('backend/asset/img/avatar/avatar-1.png') }}" class="rounded-circle mr-1" width="30" height="30">
                <div class="d-sm-none d-lg-inline-block">Hi, {{ $me->name }}</div>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <div class="dropdown-title">
                    {{ ucfirst($me->role ?? '') }}
                    @if ($me->email)<div class="text-muted small font-weight-normal">{{ $me->email }}</div>@endif
                </div>

                <a href="{{ route('superadmin.profile.show') }}" class="dropdown-item has-icon">
                    <i class="far fa-user"></i> Profil saya
                </a>
                <a href="{{ route('superadmin.audit-logs', ['user_id' => $me->id]) }}" class="dropdown-item has-icon">
                    <i class="fas fa-bolt"></i> Aktivitas saya
                </a>
                @if ($isStaff)
                    <a href="{{ route('superadmin.settings.show') }}" class="dropdown-item has-icon">
                        <i class="fas fa-cog"></i> Pengaturan sistem
                    </a>
                @endif

                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item has-icon text-danger"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </li>
    </ul>
</nav>