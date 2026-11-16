<?php

namespace App\Http\Controllers;

use App\Http\Middleware\VenueAccessGate;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueAccessController extends Controller
{
    private const COPY = [
        'en' => ['title' => 'Private preview', 'lead' => 'This site is not public yet. Enter the access code you were given.', 'label' => 'Access code', 'button' => 'Open', 'wrong' => 'That code is not correct.'],
        'ja' => ['title' => '限定公開', 'lead' => 'このサイトはまだ公開されていません。お知らせしたアクセスコードを入力してください。', 'label' => 'アクセスコード', 'button' => '開く', 'wrong' => 'コードが正しくありません。'],
        'id' => ['title' => 'Pratinjau privat', 'lead' => 'Situs ini belum dibuka untuk umum. Masukkan kode akses yang Anda terima.', 'label' => 'Kode akses', 'button' => 'Buka', 'wrong' => 'Kode tidak sesuai.'],
        'th' => ['title' => 'ดูตัวอย่างแบบส่วนตัว', 'lead' => 'เว็บไซต์นี้ยังไม่เปิดสาธารณะ กรุณากรอกรหัสเข้าใช้งานที่ได้รับ', 'label' => 'รหัสเข้าใช้งาน', 'button' => 'เปิด', 'wrong' => 'รหัสไม่ถูกต้อง'],
        'zh' => ['title' => '私人預覽', 'lead' => '此網站尚未公開，請輸入提供給您的存取碼。', 'label' => '存取碼', 'button' => '開啟', 'wrong' => '存取碼不正確。'],
    ];

    public function show(Request $request, string $venueSlug): View|RedirectResponse
    {
        $venue = Venue::where('slug', $venueSlug)->firstOrFail();

        if (! $venue->isGated() || VenueAccessGate::isUnlocked($request, $venue)) {
            return redirect()->route('venue.show', ['venueSlug' => $venue->slug]);
        }

        $locale = $this->locale($request, $venue);

        return view('venue.access', ['venue' => $venue, 'locale' => $locale, 'copy' => self::COPY[$locale], 'supportedLocales' => array_keys(self::COPY)]);
    }

    public function store(Request $request, string $venueSlug): RedirectResponse
    {
        $venue = Venue::where('slug', $venueSlug)->firstOrFail();
        $locale = $this->locale($request, $venue);

        if (! $venue->isGated()) {
            return redirect()->route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]);
        }

        $request->validate(['code' => ['required', 'string', 'max:100']]);

        if (! $venue->checkAccessCode((string) $request->input('code'))) {
            return back()->withInput($request->except('code'))->withErrors(['code' => self::COPY[$locale]['wrong']]);
        }

        $request->session()->regenerate();
        $request->session()->push(VenueAccessGate::SESSION_KEY, $venue->id);

        return redirect()->route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]);
    }

    private function locale(Request $request, Venue $venue): string
    {
        $lang = $request->input('lang', $request->query('lang'));

        return array_key_exists((string) $lang, self::COPY) ? $lang : (array_key_exists($venue->default_locale, self::COPY) ? $venue->default_locale : 'en');
    }
}
