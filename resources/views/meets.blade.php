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
                            @if($teachers)
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="teacher"
                                            name="teacher" aria-label="Профессор-ўқитувчини танланг">
                                        <option selected disabled></option>
                                        @foreach($teachers as $teacher)
                                            <option value="{{ $teacher->hemis_id }}">
                                                {{ $teacher->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <label for="teacher">Профессор-ўқитувчини танланг</label>
                                </div>
                            @endif

                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="course" value="1" id="course_1"
                                       checked>
                                <label class="btn btn-outline-primary" for="course_1">1-курс</label>

                                <input type="radio" class="btn-check" name="course" value="2" id="course_2">
                                <label class="btn btn-outline-primary" for="course_2">2-курс</label>
                            </div>

                            <div class="row">
                                <div class="col-md-9">
                                    <div class="small text-danger">
                                        Рўйхатда фақат бўш гуруҳлар кўринади.
                                    </div>
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="group"
                                                name="group" aria-label="Гуруҳни танланг" required>
                                            <option selected disabled></option>
                                            @foreach($groups as $group)
                                                @if(\App\Models\Application::in_empty_group($group->hemis_id))
                                                    <option value="{{ $group->hemis_id }}">
                                                        {{ $group->name }}
                                                        ({{ substr($group->email, 0, 2) }}***{{ substr($group->email, -9) }})
                                                    </option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <label for="group">Гуруҳни танланг</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small">&nbsp;</div>
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
    @if (Session::has('error'))
        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div id="liveToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <strong class="me-auto">Тизимда хатолик</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"
                            aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    {!! Session::get('error') !!}
                </div>
            </div>
        </div>
    @endif
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
