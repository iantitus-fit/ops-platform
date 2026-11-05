@component('layouts.customer', ['title' => 'Please wait a moment | '.$businessName, 'businessName' => $businessName, 'phone' => $phone, 'phoneText' => $phoneText])
    <x-booking.notice heading="Please wait a moment">There have been too many tries. Wait a minute, then try again.</x-booking.notice>
@endcomponent
