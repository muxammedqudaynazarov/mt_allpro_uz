@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="text-center">
                            <img src="{{ asset('logo.jpg') }}" class="w-50" alt="">
                            <div class="h4">
                                Berdaq nomidagi Qoraqalpoq davlat univesiteti Masofaviy taʼlimni boshqarish tizimi HEMIS
                                tizimi bilan integratsiyasi qilingan va natijada dars jadvalini Online ko‘rish
                                imkoniyatlari yaratilgan.
                            </div>
                            <div class="h4">
                                Quyidagi kurslardan birini tanlang?
                            </div>
                            <div>
                                <div class="row">
                                    <div class="col-6">
                                        <a href="{{ url('course/1') }}" class="btn btn-outline-success w-100 py-2">
                                            1-kurs
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="{{ url('course/2') }}" class="btn btn-outline-success w-100 py-2">
                                            2-kurs
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
