@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Дарс яратиш</div>
                            <div>
                                <a href="{{ url('/home') }}" class="btn btn-success btn-sm">
                                    Бекор қилиш
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="?" method="post">
                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="course" value="1" id="course_1"
                                       checked>
                                <label class="btn btn-outline-primary" for="course_1">1-курс</label>

                                <input type="radio" class="btn-check" name="course" value="2" id="course_2">
                                <label class="btn btn-outline-primary" for="course_2">2-курс</label>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="group"
                                                name="group" aria-label="Floating label select example">
                                            <option selected disabled></option>
                                            @foreach($groups as $group)
                                                <option value="{{ $group->hemis_id }}">
                                                    {{ $group->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <label for="group">Гуруҳни танланг</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="duration" disabled
                                               name="duration" value="80" placeholder="Дарс давомийлиги (мин.)">
                                        <label for="duration">Дарс давомийлиги (мин.)</label>
                                    </div>
                                </div>
                            </div>

                            @csrf
                            <div class="form-floating mb-3">
                                <textarea class="form-control" placeholder="Дарс мавзуси (мажбурий эмас)"
                                          id="topic" name="topic" style="min-height: 100px"></textarea>
                                <label for="topic">Дарс мавзуси (мажбурий эмас)</label>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-success w-100">
                                    Яратиш
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $("input[name='course']").on('change', function () {
            let course = $(this).val();
            $.get("{{ url('/home/course') }}/" + course, function (data) {
                $('#group').empty().append(data);
            });
        });
    </script>
@endsection
