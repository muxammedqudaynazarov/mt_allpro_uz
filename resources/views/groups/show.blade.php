@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="justify-content-center">
            <div class="card">
                <div class="card-header fw-bold">
                    <div class="d-flex">
                        <div class="flex-grow-1 py-1">
                            {{ $group->name }}
                        </div>
                        @can('groups:create')
                            <div class="mx-1">
                                <a href="{{ route('groups.create') }}" class="btn btn-success btn-sm">
                                    Гуруҳ қўшиш
                                </a>
                            </div>
                        @endcan
                        <div class="mx-1">
                            <a href="{{ route('groups.index') }}" class="btn btn-success btn-sm">
                                Рўйхатга қайтиш
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <div class="border rounded p-2">
                                <div class="mx-2 small text-muted">
                                    HEMIS ID
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->hemis_id }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Гуруҳ номи
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->name }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Электрон почта
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->email }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Курси
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->course }}-курс
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Маъсул ходим
                                </div>
                                <div class="mx-2 fw-bold">
                                    @if ($group->admin_id)
                                        {{ $group->admin->name }}
                                    @else
                                        Белгиланмаган
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    API токен
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->api }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Account ID
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->account_id }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Client ID
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->client_id }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Secret client
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->client_secret }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Яратилган вақти
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->created_at }}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border mb-3 rounded p-2">
                                <div class="mx-2 small text-muted">
                                    Янгиланган вақти
                                </div>
                                <div class="mx-2 fw-bold">
                                    {{ $group->updated_at }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
