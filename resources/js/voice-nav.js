/**
 * Spoken navigation: turns a short utterance ("halls", "buka reservasi", "ホールを見せて", "english") into an
 * action for the page. Pure functions (no DOM), so it can be tested with plain node.
 * Whole-utterance matching only: a real question ("how much is the hall?") never navigates, it goes to the AI.
 */

const normalise = (text) => String(text || '')
    .normalize('NFKC')
    .toLowerCase()
    .replace(/[.,!?。、，！？"“”「」'’:;]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

// Polite filler around a menu name, per language; removed before matching.
const FILLER = [
    /\b(?:please|go to|go|open|show me|show|take me to|take me|navigate to|let us see|lets see|i want to see|i want to go to|i want to|switch to|change language to|change to|language|the|page|menu|section|to)\b/g,
    /\b(?:tolong|buka|ke|halaman|lihat|tampilkan|menu|ya|dong|saya|mau|ingin|ganti|ubah|bahasa)\b/g,
    /(?:を|へ|に|は|開いて|開く|見せて|見たい|行きたい|行って|移動して|移動|お願いします|お願い|ください|戻って|戻る|もどって|もどる|ページ|画面|メニュー|にして|に変更|に切り替え|切り替えて|言語)/g,
    /(?:ไปที่|ไปหน้า|ไป|เปิด|ดู|หน้า|เมนู|ครับ|ค่ะ|คะ|ภาษา|เปลี่ยน|กลับไป)/g,
    /(?:請|请|打開|打开|前往|去|看|顯示|显示|帶我去|带我去|我要|我想|頁面|页面|選單|菜单|吧|啊|換成|切換|切换|語言|语言)/g,
];

export function stripFiller(text) {
    let value = normalise(text);
    FILLER.forEach((pattern) => { value = value.replace(pattern, ' '); });

    return value.replace(/\s+/g, ' ').trim();
}

const MENU = {
    lobby: /^(?:home|welcome|lobby|start|main|beranda|awal|utama|ホーム|ロビー|トップ|ウェルカム|หน้าแรก|หน้าหลัก|ต้อนรับ|首頁|首页|歡迎|主頁)$/,
    halls: /^(?:halls?|venues?|rooms?|plans?|packages?|hall pernikahan|gedung|ruang|ruangan|paket|ホール|会場一覧|プラン|パッケージ|ฮอลล์|แพ็กเกจ|宴會廳|宴会厅|方案)$/,
    services: /^(?:services?|options?|wedding services?|layanan|opsi|layanan pernikahan|サービス|オプション|ウェディングサービス|บริการ|บริการแต่งงาน|婚禮服務|服務|服务)$/,
    info: /^(?:info|information|venue information|policies|policy|about|access|faq|informasi|informasi gedung|tentang|kebijakan|akses|会場ご案内|会場案内|案内|情報|会場情報|インフォメーション|アクセス|ข้อมูล|ข้อมูลสถานที่|場地資訊|場地信息|資訊|资讯)$/,
    reservation: /^(?:reservations?|booking|book|reserve|hold date|hold my date|hold your date|reservasi|pesan|pemesanan|amankan tanggal|予約|日程|日程仮予約|仮予約|日程を仮予約|จอง|จองวันจัดงาน|預約|預約日期|预约)$/,
    staff: /^(?:staff|team|talk team|talk team|contact|contact us|talk staff|wedding team|tim|staf|hubungi|bicara tim|bicara dengan tim|スタッフ|チーム|相談|スタッフ相談|スタッフに相談|ทีมงาน|คุยกับทีมงาน|ติดต่อทีมงาน|ติดต่อ|婚禮團隊|團隊|团队|聯絡|联系)$/,
};

const LANGUAGES = {
    en: /^(?:english|inggris|bahasa inggris|英語|英文|อังกฤษ|ภาษาอังกฤษ)$/,
    id: /^(?:indonesian|indonesia|bahasa indonesia|インドネシア語|インドネシア|อินโดนีเซีย|印尼文|印尼語)$/,
    ja: /^(?:japanese|jepang|日本語|日語|日文|ญี่ปุ่น|ภาษาญี่ปุ่น)$/,
    th: /^(?:thai|thailand|タイ語|タイ|ไทย|ภาษาไทย|泰文|泰語|泰语)$/,
    zh: /^(?:chinese|mandarin|cina|tiongkok|中国語|中文|繁體中文|繁体中文|จีน|ภาษาจีน)$/,
};

const SCROLL = {
    scrollDown: /^(?:scroll )?(?:down|further down|lower)$|^(?:scroll )?bawah$|^(?:turun|gulir bawah)$|^(?:下|下へ|下に)(?:スクロール)?$|^スクロール下$|^(?:ลง|เลื่อนลง|ล่าง)$|^(?:向下|往下|下滑|下捲|下卷)$/,
    scrollUp: /^(?:scroll )?(?:up|back up|higher)$|^(?:scroll )?atas$|^(?:naik|gulir atas)$|^(?:上|上へ|上に)(?:スクロール)?$|^スクロール上$|^(?:ขึ้น|เลื่อนขึ้น|บน)$|^(?:向上|往上|上滑|上捲|上卷)$/,
};

const BACK = /^(?:back|go back|previous page|return|kembali|balik|mundur|戻る|戻って|前のページ|กลับ|ย้อนกลับ|返回|上一頁|上一页)$/;

const HANGUP = /^(?:stop|stop listening|hang up|end call|that is all|that's all|berhenti|stop mic|matikan mic|マイクを止めて|マイク停止|通話終了|止めて|やめて|หยุด|หยุดฟัง|停止|停止聆聽|結束通話|掛斷)$/;

/**
 * @returns {{type: 'menu', key: string}|{type: 'lang', code: string}|{type: 'scrollDown'|'scrollUp'|'hangup'|'back'}|{type: 'item', query: string}|null}
 */
export function interpret(text) {
    const raw = normalise(text);
    if (!raw) return null;

    if (HANGUP.test(raw) || HANGUP.test(raw.replace(/\s/g, ''))) return { type: 'hangup' };

    if (BACK.test(raw) || BACK.test(raw.replace(/\s/g, ''))) return { type: 'back' };

    const stripped = stripFiller(text);
    if (!stripped) return null;
    const compact = stripped.replace(/\s/g, '');

    for (const [type, pattern] of Object.entries(SCROLL)) {
        if (pattern.test(raw) || pattern.test(stripped) || pattern.test(compact)) return { type };
    }

    for (const [key, pattern] of Object.entries(MENU)) {
        if (pattern.test(stripped) || pattern.test(compact)) return { type: 'menu', key };
    }

    for (const [code, pattern] of Object.entries(LANGUAGES)) {
        if (pattern.test(stripped) || pattern.test(compact)) return { type: 'lang', code };
    }

    // A short phrase can still be the name of a hall, a service or a tab: the page decides whether one matches.
    const words = stripped.split(' ').length;
    if (words <= 6 && compact.length <= 40) return { type: 'item', query: compact };

    return null;
}

/** Picks the candidate whose label best matches a spoken query (labels and query compared without spaces). */
export function bestLabelMatch(query, candidates) {
    const wanted = normalise(query).replace(/\s/g, '');
    if (wanted.length < 2) return null;

    let best = null;
    candidates.forEach((candidate) => {
        const label = normalise(candidate.label).replace(/\s/g, '');
        if (label.length < 2) return;

        let score = 0;
        if (label === wanted) score = 1000 + label.length;
        else if (label.includes(wanted) && wanted.length >= 3) score = 500 + wanted.length;
        else if (wanted.includes(label) && label.length >= 3) score = 300 + label.length;

        if (score > 0 && (!best || score > best.score)) best = { candidate, score };
    });

    return best?.candidate ?? null;
}

/* ───────────────────────── DOM side: carries the actions out on the page ───────────────────────── */

const visible = (element) => !!element && element.getClientRects().length > 0 && !element.closest('[hidden]');
const textOf = (element) => (element.getAttribute('aria-label') || element.textContent || '').replace(/\s+/g, ' ').trim();

let toastTimer = null;

/** A small spoken-feedback line near the top of the stage ("Opening Halls"). */
export function toast(text) {
    if (!text) return;

    let element = document.querySelector('[data-voicenav-toast]');
    if (!element) {
        element = document.createElement('p');
        element.className = 'voicenav-toast';
        element.dataset.voicenavToast = '';
        element.setAttribute('role', 'status');
        element.setAttribute('aria-live', 'polite');
        (document.querySelector('.stage') || document.body).appendChild(element);
    }

    element.textContent = text;
    element.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => element.classList.remove('is-visible'), 3600);
}

function scroller() {
    const candidates = [...document.querySelectorAll('dialog[open], .lobby-content, .hall-nav, .info-tabpanels, .stage-panels')];

    return candidates.find((element) => visible(element) && element.scrollHeight > element.clientHeight + 8
        && ['auto', 'scroll'].includes(getComputedStyle(element).overflowY)) || null;
}

function scrollPage(direction) {
    const target = scroller();
    const distance = (target ? target.clientHeight : window.innerHeight) * 0.8 * direction;
    (target || window).scrollBy({ top: distance, behavior: 'smooth' });
}

/** Closes the topmost pop-up, if any. */
export function closeTop() {
    const dialog = [...document.querySelectorAll('dialog[open]')].pop();
    if (!dialog) return false;
    dialog.close();

    return true;
}

/** "next" / "previous" on whatever the page is showing: an open photo, the booking steps, or the next hall/service. */
export function stepPage(direction) {
    const dialog = [...document.querySelectorAll('dialog[open]')].pop();
    const inside = (selector) => (dialog ? [...dialog.querySelectorAll(selector)] : []).find(visible);

    const forward = direction > 0;
    const photo = inside(forward ? '[data-lightbox-next], [data-photo-next]' : '[data-lightbox-prev], [data-photo-prev]');
    if (photo) { photo.click(); return true; }
    if (dialog) return false;

    const wizard = [...document.querySelectorAll(forward ? '[data-wizard-next]' : '[data-wizard-back]')].find((el) => visible(el) && !el.disabled);
    if (wizard) { wizard.click(); return true; }

    const link = [...document.querySelectorAll(forward ? 'a[rel="next"]' : 'a[rel="prev"]')].find(visible);
    if (link) { link.click(); return true; }

    return false;
}

function goBack() {
    if (closeTop()) return true;

    const wizardBack = [...document.querySelectorAll('[data-wizard-back]')].find((el) => visible(el) && !el.disabled);
    if (wizardBack) { wizardBack.click(); return true; }

    const close = [...document.querySelectorAll('a.panel-close')].find(visible);
    if (close) { close.click(); return true; }

    const home = document.querySelector('[data-menu-key="lobby"]');
    if (home && home.pathname !== window.location.pathname) { home.click(); return true; }

    return false;
}

/** Everything on this page that can be opened by saying its name. */
function nameables() {
    const items = [];
    const add = (element, label) => { if (visible(element) && label) items.push({ element, label }); };

    document.querySelectorAll('a[data-stage-inner]:not([rel])').forEach((link) => {
        const name = link.querySelector('.hall-nav-name, .service-card-title');
        add(link, name ? textOf(name) : textOf(link));
    });
    document.querySelectorAll('[data-area-tab]').forEach((tab) => add(tab, textOf(tab.querySelector('.area-tab-label') || tab)));
    document.querySelectorAll('.info-tablist label').forEach((label) => add(label, textOf(label)));

    return items;
}

/**
 * Carries out a command from `interpret()`. Returns true when the page handled it (nothing goes to the AI then).
 * `labels` are the page's own phrases for the toasts.
 */
export function perform(action, labels = {}) {
    if (!action) return false;

    switch (action.type) {
        case 'menu': {
            const link = document.querySelector(`[data-menu-key="${action.key}"]`);
            if (!link) return false;
            if (link.pathname === window.location.pathname) {
                toast(labels.here);
                return true;
            }
            toast(`${labels.opening || ''} ${textOf(link)}`.trim());
            link.click();

            return true;
        }
        case 'lang': {
            const link = document.querySelector(`.stage-lang a[lang="${action.code}"]`);
            if (!link) return false;
            if (link.hasAttribute('aria-current')) {
                toast(labels.here);
                return true;
            }
            link.click();
            window.location.href = link.href;

            return true;
        }
        case 'scrollDown':
            scrollPage(1);
            return true;
        case 'scrollUp':
            scrollPage(-1);
            return true;
        case 'back':
            return goBack();
        case 'item': {
            const match = bestLabelMatch(action.query, nameables());
            if (!match) return false;
            toast(`${labels.opening || ''} ${match.label}`.trim());
            match.element.click();

            return true;
        }
        default:
            return false;
    }
}
