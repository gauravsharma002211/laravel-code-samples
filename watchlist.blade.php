@extends('layouts.main.app')

@section('title',  __('heroic/breach-monitoring.breach_monitoring'))
@section('page-name', __('heroic/breach-monitoring.breach_monitoring'))

@section('content')
@include('datacompromise.partials.dashboard.new-search-form')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Post-->
    <div class="post d-flex flex-column-fluid" id="kt_post">
        <!--begin::Container-->
        <div id="kt_content_container" class="container-xxl">
            @include('datacompromise.partials.dashboard.search-result-table')
                <div class="g-5 g-xl-8">
                    <div class="card shadow-sm">
                        <div class="card-body card-scroll">
                            <div class="modal fade" id="view-data" tabindex="-1" role="dialog">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h3 class="modal-title">{{ __('heroic/breach-monitoring.upgrade_for_advance') }}</h3>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                                        </div>
                                        <div class="modal-body">
                                            {{ __('heroic/breach-monitoring.subscription_plans') }}
                                        </div>
                                        <div class="modal-footer">
                                            <a href="/billing-account" class="btn btn-primary" role="button">{{ __('heroic/breach-monitoring.upgrade_my_account') }}</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @include('datacompromise.partials.breach-details-modal')
                            <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                                <li class="nav-item">
                                    <a class="nav-link active" data-bs-toggle="tab" href="#kt_tab_pane_1">{{ __('heroic/breach-monitoring.monitored_identities') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#kt_tab_pane_2">{{ __('heroic/breach-monitoring.breach_events') }}</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-bs-toggle="tab" href="#kt_tab_pane_3">{{ __('heroic/breach-monitoring.breached_data_sources') }}</a>
                                </li>
                            </ul>
                            <div class="tab-content" id="myTabContent">
                                <div class="tab-pane fade show active" id="kt_tab_pane_1" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-12 ">
                                            <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#add-new-monitor-modal" onclick="openAddMonitorModal()">{{ __('heroic/breach-monitoring.add_new_to_monitor') }}</button>
                                        </div>
                                    </div>
                                    <table class="table table-flush align-middle table-row-bordered border table-row-solid gy-4 gs-9" id="monitor-identity">
                                        <thead class="border-gray-200 fs-5 fw-bold bg-lighten">
                                            <tr>
                                                <th>{{ __('heroic/breach-monitoring.identity') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.identity_type') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.status') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.last_discovered') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.breach_records') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.verified') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.action') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fs-6 fw-bold text-gray-600"></tbody>
                                    </table>
                                </div>

                                <div class="tab-pane fade" id="kt_tab_pane_2" role="tabpanel">
                                    <table class="table table-flush align-middle table-row-bordered border table-row-solid gy-4 gs-9" id="itemDetail">
                                        <thead class="border-gray-200 fs-5 fw-bold bg-lighten">
                                            <tr>
                                                <th>{{ __('heroic/breach-monitoring.breach_entity') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.breach_name') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.breach_date') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.breach_data_points') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.breach_details') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fs-6 fw-bold text-gray-600"></tbody>
                                    </table>
                                </div>

                                <div class="tab-pane fade" id="kt_tab_pane_3" role="tabpanel">
                                    <table class="table table-flush align-middle table-row-bordered border table-row-solid gy-4 gs-9" id="breach-sources">
                                        <thead class="border-gray-200 fs-5 fw-bold bg-lighten">
                                            <tr>
                                                <th>{{ __('heroic/breach-monitoring.source_name') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.no_of_records') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.breached_data_types') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.published_date') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.category') }}</th>
                                                <th>{{ __('heroic/breach-monitoring.breach_details') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fs-6 fw-bold text-gray-600"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
    </div>
</div>
<div class="modal fade" id="add-new-monitor-modal" tabindex="-1" >
    <div class="modal-dialog">
       <div class="modal-content">
          <div class="modal-header">
             <h5 class="modal-title">{{ __('heroic/breach-monitoring.add_new_monitor_identity') }}</h5>
            <!--begin::Close-->
            <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                <!--begin::Svg Icon | path: icons/duotune/arrows/arr061.svg-->
                <span class="svg-icon svg-icon-2x">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"></rect>
                        <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"></rect>
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </div>
            <!--end::Close-->
          </div>
          <div class="modal-body">
             <div class="alert alert-danger" id="form-watchlist-error" style="display: none;">
                <div id="form-watchlist-error-message"></div>
             </div>
             <form class="kt-form kt-form--fit kt-form--label-align-right" id="add_to_monitor_form" action="{{route('breach-monitoring.add')}}" method="POST">
                <div data-kt-stepper-element="content" class="current">
                    <div class="w-100">
                        <div class="fv-row mb-10 fv-plugins-icon-container fv-plugins-bootstrap5-row-valid">
                            <!--begin::Label-->
                            <label class="required fs-5 fw-bold mb-2">{{ __('heroic/breach-monitoring.breach_type') }}</label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <select class="form-select form-select-solid" id="breach_type" name="breach_type" aria-label="Select example">
                                @foreach($breachMonitorTypes as $type)
                                    <option value="{{$type['name']}}">{{$type['display_name']}}</option>
                                @endforeach
                            </select>
                            <!--end::Input-->
                        </div>
                    </div>
                    <div class="w-100">
                        <div class="fv-row mb-10 fv-plugins-icon-container fv-plugins-bootstrap5-row-valid">
                            <!--begin::Label-->
                            <label class="required fs-5 fw-bold mb-2">{{ __('heroic/breach-monitoring.breach_term') }}</label>
                            <!--end::Label-->
                            <!--begin::Input-->
                            <input type="text" id="breach_term" name="breach_term" class="form-control form-control-lg form-control-solid" placeholder="Email, username, or IP" >
                            <span class="text-warning" id="wrn-domain"></span>
                            <span id="breach_term_error" class="invalid-feedback" role="alert">
                                <strong>{{ $errors->first('breach_term') }}</strong>
                            </span>
                            <!--end::Input-->
                        </div>
                    </div>
                </div>
                <div class="kt-portlet__foot kt-portlet__no-border kt-portlet__foot--fit">
                   <div class="kt-form__actions">
                      <div class="row">
                         <div class="col-lg-5"></div>
                         <div class="col-lg-7">
                            <button type="submit" class="btn btn-primary" id="addEntityButtonNew">{{ __('heroic/breach-monitoring.submit') }}</button>
                         </div>
                      </div>
                   </div>
                </div>
             </form>
          </div>
       </div>
    </div>
</div>
@endsection
