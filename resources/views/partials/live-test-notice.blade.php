@if(config('live-test.enabled'))
<div class="helper-box" role="status" style="margin-bottom:20px;color:#755c3c;background:#fff5df;border-color:#ebd9af">
    <p>نسخه آزمایشی آنلاین: ثبت‌نام و کار با پروژه‌ها فعال است. اطلاعات این جلسه موقت‌اند و پس از پایان آن حذف می‌شوند.
    @if(config('live-test.expires_at'))
        پایان جلسه: {{ strtr(\Illuminate\Support\Carbon::parse(config('live-test.expires_at'))->timezone('Asia/Tehran')->format('H:i'), ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']) }} به وقت تهران.
    @endif
    </p>
</div>
@endif
