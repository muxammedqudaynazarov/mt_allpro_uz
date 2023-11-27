@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Darslar jadvali</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($groups as $group)
                                <div class="col-md-6 mb-2">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="fw-bold">
                                                <a href="{{ url('groups/' . $group->hemis_id) }}"
                                                   class="stretched-link">

                                                </a>
                                                {{ $group->name }}
                                            </div>
                                            <div class="small">
                                                {{ $group->course }}-kurs
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
