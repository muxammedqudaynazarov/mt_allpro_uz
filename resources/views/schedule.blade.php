@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header fw-bold">
                        <div class="d-flex">
                            <div class="flex-grow-1 py-1">Dars jadvali</div>
                            <div>
                                <a href="{!! url('/') !!}" class="btn btn-success btn-sm">
                                    Ortga qaytish
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                            <tr class="text-center">
                                <th>Kun, sana</th>
                                <th>Dars</th>
                                <th>Vaqti</th>
                            </tr>
                            </thead>
                            <tbody class="small">
                            <tr class="align-middle">
                                <td rowspan="3" class="text-center">
                                    Dushanba
                                    <br>
                                    {!! date('d.m.Y', $begin) !!}
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Monday'][1]['lesson'] !!}
                                    </div>

                                    {!! $data['Monday'][1]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Monday'][1]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Monday'][2]['lesson'] !!}
                                    </div>

                                    {!! $data['Monday'][2]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Monday'][2]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Monday'][3]['lesson'] !!}
                                    </div>

                                    {!! $data['Monday'][3]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Monday'][3]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td rowspan="3" class="text-center">
                                    Seshanba
                                    <br>
                                    {!! date('d.m.Y', $begin + (1 * 86400)) !!}
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Tuesday'][1]['lesson'] !!}
                                    </div>

                                    {!! $data['Tuesday'][1]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Tuesday'][1]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Tuesday'][2]['lesson'] !!}
                                    </div>

                                    {!! $data['Tuesday'][2]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Tuesday'][2]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Tuesday'][3]['lesson'] !!}
                                    </div>

                                    {!! $data['Tuesday'][3]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Tuesday'][3]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td rowspan="3" class="text-center">
                                    Chorshanba
                                    <br>
                                    {!! date('d.m.Y', $begin + (2 * 86400)) !!}
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Wednesday'][1]['lesson'] !!}
                                    </div>

                                    {!! $data['Wednesday'][1]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Wednesday'][1]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Wednesday'][2]['lesson'] !!}
                                    </div>

                                    {!! $data['Wednesday'][2]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Wednesday'][2]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Wednesday'][3]['lesson'] !!}
                                    </div>

                                    {!! $data['Wednesday'][3]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Wednesday'][3]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td rowspan="3" class="text-center">
                                    Payshanba
                                    <br>
                                    {!! date('d.m.Y', $begin + (3 * 86400)) !!}
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Thursday'][1]['lesson'] !!}
                                    </div>

                                    {!! $data['Thursday'][1]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Thursday'][1]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Thursday'][2]['lesson'] !!}
                                    </div>

                                    {!! $data['Thursday'][2]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Thursday'][2]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Thursday'][3]['lesson'] !!}
                                    </div>
                                    {!! $data['Thursday'][3]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Thursday'][3]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td rowspan="3" class="text-center">
                                    Juma
                                    <br>
                                    {!! date('d.m.Y', $begin + (4 * 86400)) !!}
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Friday'][1]['lesson'] !!}
                                    </div>

                                    {!! $data['Friday'][1]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Friday'][1]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Friday'][2]['lesson'] !!}
                                    </div>

                                    {!! $data['Friday'][2]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Friday'][2]['value'] !!}
                                </td>
                            </tr>
                            <tr class="align-middle">
                                <td>
                                    <div class="fw-bold">
                                        {!! $data['Friday'][3]['lesson'] !!}
                                    </div>

                                    {!! $data['Friday'][3]['teacher'] !!}
                                </td>
                                <td class="text-center">
                                    {!! $data['Friday'][3]['value'] !!}
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
