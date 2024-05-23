@extends('layouts.main.app')
@section('title', __('heroic/dashboard.dashboard'))
@section('page-name', __('heroic/dashboard.dashboard'))

@section('content')
    <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                @if (
                    $loggedInUserOwner->created_at->diffInDays(now()) <= 8 &&
                        $userReferralsListings->where('status', 'Completed')->count() < $bonusDescription->total_user)
                    <h2>Hi {{ $loggedInUser->first_name }}, welcome to HEROIC!</h2>

                    <p class="fs-5 mt-3 mb-6">For the next <b>8 days</b>, you can invite
                        and verify up
                        to 5 friends and secure
                        {{ $rewardPoint }}
                        HERO's for each referral.
                        <br>Your invite will also help them to reserve HERO's early, and help expand the HEROIC
                        Universe.
                    </p>
                @endif
                <div class="row mb-6">
                    <div class="col-lg-8 col-md-6 h-100">                        
                        @include('dashboard-v2.invite-your-friends')
                        @include('dashboard-v2.hero-balance')
                    </div>
                    <div class="col-lg-4 col-md-6 mt-6 mt-sm-0 mt-md-0 mt-lg-0">
                        <livewire:dashboard.referrals :owner="$loggedInUserOwner">
                    </div>
                </div>
                <div class="row mb-6">
                    <div class="col-lg-8 col-md-6 h-100">
                        <div class="row mb-6">
                            <div class="col-lg-8 col-md-6">
                                @include('dashboard-v2.check-for-breaches')
                            </div>
                            <div class="col-lg-4 col-md-6 mt-6 mt-sm-0 mt-md-0 mt-lg-0">
                                @include('dashboard-v2.security-score')
                            </div>
                        </div>
                        <div class="relative">
                            @include('dashboard-v2.achievements')
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mt-6 mt-sm-0 mt-md-0 mt-lg-0">
                        @include('dashboard-v2.my-profile-vertical')
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-4">
                        @include('dashboard-v2.secure-your-identity')
                    </div>
                    <div class="col-lg-8 mt-6 mt-sm-0 mt-md-0 mt-lg-0">
                        @include('dashboard-v2.education')
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('dashboard.greetings-modal')
@endsection
