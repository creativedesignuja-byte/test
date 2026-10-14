<?php

namespace Database\Seeders;

use App\Models\Label;
use App\Models\Notice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is only available locally.');
        }
        $password = env('DEMO_PASSWORD');
        if (! $password) {
            throw new \RuntimeException('Set DEMO_PASSWORD locally before seeding.');
        }
        if (User::where('email', 'sara@example.test')->exists()) {
            return;
        }
        $people = [['سارا محمدی', 'sara@example.test'], ['علی رضایی', 'ali@example.test'], ['نیلوفر احمدی', 'niloufar@example.test'], ['امیر حسینی', 'amir@example.test']];
        $users = collect($people)->map(fn ($p) => User::create(['name' => $p[0], 'email' => $p[1], 'password' => $password]));
        $owner = $users[0];
        $labelData = [['طراحی', '#987bb9', 'کارهای مربوط به تجربه و رابط کاربری'], ['توسعه', '#568d7e', 'پیاده‌سازی و توسعه محصول'], ['نیاز به بررسی', '#b79b55', 'منتظر بازبینی تیم'], ['فوری', '#c67564', 'نیازمند توجه در اولین فرصت'], ['پژوهش', '#7395ad', 'شناخت کاربر و نیازهای محصول']];
        $specs = [['سامانه رزرو هتل', 'تجربه‌ای ساده و دلنشین برای پیدا کردن اقامت بعدی', '#ce9466', 'راه‌اندازی نسخه اول سامانه رزرو آنلاین هتل', 'کاربران بتوانند هتل جست‌وجو کنند، جزئیات را ببینند و رزرو خود را نهایی کنند.'], ['بازطراحی وب‌سایت اُجا', 'روایتی تازه از هویت استودیو و کارهایی که می‌سازیم', '#95a689', 'ارائه وب‌سایت جدید استودیو اُجا', 'معرفی خدمات، نمایش نمونه‌کارها و دریافت درخواست همکاری.'], ['اپلیکیشن مدیریت مالی', 'کمک به ساختن عادت‌های مالی بهتر، هر روز', '#a296bf', 'آماده‌سازی نسخه آزمایشی همراه مالی', 'کاربر بتواند درآمد و هزینه ثبت کند و گزارش ماهانه ببیند.']];
        foreach ($specs as $pi => $spec) {
            $p = Project::create(['name' => $spec[0], 'description' => $spec[1], 'color' => $spec[2], 'created_by' => $owner->id]);
            foreach ($users as $i => $u) {
                $p->members()->attach($u->id, ['role' => $i === 0 ? 'owner' : 'member']);
            }
            $p->goal()->create(['title' => $spec[3], 'description' => 'با همکاری تیم، یک تجربه کاربردی، قابل اعتماد و ساده برای کاربران می‌سازیم.', 'success_criteria' => $spec[4], 'start_date' => today()->subDays(23)->toDateString(), 'end_date' => today()->addDays(32 + $pi * 10)->toDateString()]);
            $stages = collect(['پژوهش', 'طراحی', 'توسعه', 'آزمون', 'انتشار'])->map(fn ($title, $i) => $p->stages()->create(['title' => $title, 'description' => 'گام '.($i + 1).' در مسیر رسیدن به هدف', 'position' => $i, 'start_date' => today()->addDays(-23 + $i * 11)->toDateString(), 'end_date' => today()->addDays(-13 + $i * 11)->toDateString()]));
            $labels = collect($labelData)->map(fn ($l) => Label::create(['name' => $l[0], 'color' => $l[1], 'description' => $l[2], 'project_id' => $p->id, 'created_by' => $owner->id]));
            if ($pi === 0) {
                $titles = [['بررسی تجربه رزرو در رقبا', 'مصاحبه با کاربران هدف', 'تدوین مسیر سفر کاربر'], ['طراحی وایرفریم صفحه اصلی', 'طراحی صفحه جزئیات هتل', 'طراحی جریان نهایی کردن رزرو'], ['پیاده‌سازی جست‌وجوی هتل', 'توسعه رابط برنامه‌نویسی رزرو', 'اتصال درگاه پرداخت'], ['آزمون تجربه رزرو در موبایل', 'بررسی دسترس‌پذیری صفحات'], ['آماده‌سازی نسخه انتشار']];
            } elseif ($pi === 1) {
                $titles = [['بررسی هویت بصری فعلی', 'شناخت مخاطبان استودیو'], ['طراحی صفحه نمونه‌کارها', 'انتخاب تایپوگرافی و رنگ'], ['پیاده‌سازی صفحه اصلی', 'بهینه‌سازی تصاویر'], ['آزمون فرم تماس'], ['انتشار وب‌سایت جدید']];
            } else {
                $titles = [['تحلیل نیازهای کاربران', 'بررسی اپلیکیشن‌های مشابه'], ['طراحی داشبورد مالی', 'طراحی فرم ثبت هزینه'], ['پیاده‌سازی ثبت تراکنش', 'ساخت گزارش ماهانه'], ['آزمون محاسبات مالی'], ['ارائه نسخه آزمایشی']];
            }
            foreach ($titles as $si => $group) {
                foreach ($group as $ti => $title) {
                    $done = $si === 0 || ($pi === 1 && $si === 1) || ($pi === 0 && $si === 1 && $ti === 0);
                    $assignee = ($ti === 0 || $si === 1) ? $owner : $users[($ti % 3) + 1];
                    $t = $p->tasks()->create(['stage_id' => $stages[$si]->id, 'title' => $title, 'description' => 'این کار را با توجه به هدف اصلی پروژه و بازخوردهای تیم پیش ببریم. نتیجه نهایی برای بررسی با اعضای تیم به اشتراک گذاشته شود.', 'start_date' => today()->subDays(4)->toDateString(), 'due_date' => today()->addDays($si < 2 ? $ti - 1 : $si + $ti)->toDateString(), 'completed_at' => $done ? now()->subDays(2) : null, 'created_by' => $owner->id, 'assigned_to' => $assignee->id]);
                    $t->labels()->attach($labels[$si === 0 ? 4 : ($si === 1 ? 0 : 1)]->id);
                    if ($ti === 1) {
                        $t->labels()->attach($labels[2]->id);
                    }$t->members()->attach($users[1]->id);
                    if ($ti === 0 || $si === 1) {
                        foreach (['بررسی نیازمندی‌ها', 'آماده‌سازی نسخه اولیه', 'بازبینی و تحویل نهایی'] as $j => $sub) {
                            $t->subtasks()->create(['title' => $sub, 'position' => $j, 'completed_at' => ($done || $j === 0) ? now() : null]);
                        }
                    }if ($si === 1 && $ti === 1) {
                        $t->comments()->create(['user_id' => $users[1]->id, 'body' => 'نسخه اولیه آماده است. لطفاً بخش امکانات هتل و نمایش نظرات مسافران را هم در بررسی در نظر بگیرید.']);
                    }
                }
            }
            foreach ([['برنامه‌ریزی', 'هماهنگی تیم', 'بازبینی', 'تحویل'], ['پژوهش', 'وایرفریم', 'طراحی رابط', 'تحویل'], ['راه‌اندازی', 'رابط کاربری', 'اتصال API', 'آزمون'], ['تحلیل', 'پایگاه داده', 'توسعه API', 'آزمون']] as $ui => $path) {
                foreach ($path as $i => $title) {
                    $p->memberStages()->create(['user_id' => $users[$ui]->id, 'title' => $title, 'position' => $i, 'start_date' => today()->subDays(7)->toDateString(), 'end_date' => today()->addDays(20)->toDateString()]);
                }
            }
        }
        Notice::create(['user_id' => $owner->id, 'task_id' => 4, 'body' => 'یک قدم تازه: طراحی وایرفریم صفحه اصلی به شما سپرده شد.']);
    }
}
