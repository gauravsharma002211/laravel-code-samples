@extends('layouts.main.app')
@section('title', 'Breach Monitoring')
@section('page-name','Breach Monitoring')
@section('page-css')
    <style>
        .portlet.infoCard {
            margin-top: 12px;
            background-color: #F2F6F9;
            border: 1px solid #ddd;
            padding: 30px;
            text-align: center;
        }
        .portlet.infoCard p{
            max-width: 80% !important;
            margin: 0 auto!important;
            font-size: 15px;
            line-height: 24px;
        }
    </style>
@endsection
@section('content')
    <div class="m-content">
        @include('datacompromise.partials.modal')
        <div class="m-portlet">
            <div class="m-portlet__body">
                <ul class="nav nav-pills nav-fill" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active show" data-toggle="tab" href="#m_tabs_5_1"> Breach Search </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#m_tabs_5_2"> Monitored Identities </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#m_tabs_5_3"> Breach Events </a>
                    </li>

                </ul>

                <div class="tab-content">
                    <div class="tab-pane active show" id="m_tabs_5_1" role="tabpanel">
                            <!-- BEGIN PORTLET-->
                        <div class="m-portlet__body">
                            <header class="text-center">
                                <h2 style="font-size: 3rem;">Has Your Data Been Compromised?</h2>
                                <h4>Search for your Email, Username or IP Address</h4>
                            </header>
                            <div class="row">
                                <div class="col-xl-8 offset-xl-4 mt-5">
                                    <form class="" id="general_scan_form">
                                        <!--start search-->
                                        <div class="col-lg-offset-3 col-lg-6 col-sm-offset-3 col-sm-6">
                                            <div class="form-group">
                                                <label for="search_type" class="control-label">Search type</label>
                                                <div class="">
                                                    <select name="search_type" id="search_type" class="form-control">
                                                        <option value="email">Email</option>
                                                        <option value="username">Username</option>
                                                        <option value="ipaddress">IP Address</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="search_term" class="control-label">Search term</label>
                                                <div class="breachSearchInput">
                                                    <input type="text" class="form-control" name="search_term" id="search_term" placeholder="Email, username, or IP">

                                                </div>
                                            </div>
                                            <div class="form-group" style="text-align: center">
                                                <label class="m-checkbox">
                                                    <input type="checkbox"> Add to Monitoring
                                                    <span></span>
                                                </label>
                                            </div>


                                            <div class="text-center m-b-20">
                                                <button class="btn blue progress-button" style="padding: 6px 47px;" id="searchEntityButtonNew" data-loading="Loading.." data-finished="Search">
                                                    Search
                                                    <span class="tz-bar background-horizontal"></span></button>

                                            </div>
                                        </div>
                                        <input type="hidden" name="_token" value="GKYbQPHzlv3hk9UYHGAQoyrg3NNyGRQLSyjcxOIJ">
                                        <input type="hidden" name="eSearchTerm" id="eSearchTerm">
                                        <input type="hidden" name="eSearchType" id="eSearchType">
                                        <input type="hidden" name="example_table_search_key" id="example_table_search_key" value="">
                                        <input type="hidden" name="example_table_search_length" id="example_table_search_length" value="">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="m_tabs_5_2" role="tabpanel">
                        <div class="m-portlet__body">

                            <header class="firstHeader" style="margin-bottom: 0px;">
                                <h2 class="text-left text-center-xs">Monitored Data Points <div class="btn-group pull-right mobile-block" style="margin-bottom: 10px;">
                                        <a id="add-new-email" class="btn green-jungle text-center-xs" data-toggle="modal" style="float: none;" href="#add-email-modal">
                                            Add New <i class="fa fa-plus"></i>
                                        </a>
                                    </div></h2>
                            </header>
                            <div class="portlet box grey-cascade">
                                <div class="portlet-body" style="padding: 0;">
                                    <div id="monitored_emails_wrapper">
                                        <div id="monitored_emails_table_new_wrapper" class="dataTables_wrapper no-footer">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="m_tabs_5_3" role="tabpanel">
                        <div class="col-lg-12">
                            <div class="portlet infoCard">
                                <header>
                                    <h2>What are Breach Events?</h2>
                                    <p>A "breach" is an incident where a site's data has been made public by either hackers illegally accessing and distributing it or via a data leak resulting from a misconfigured system. Please review the compromised accounts below and follow the appropriate actions to remediate each vulnerability.</p>
                                </header>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection