import './bootstrap';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import Chart from 'chart.js/auto';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import {
    Activity,
    ArrowLeft,
    Bell,
    Bot,
    Building2,
    CalendarCheck,
    Check,
    ChevronDown,
    CircleDollarSign,
    ClipboardList,
    CreditCard,
    Droplets,
    Eye,
    EyeOff,
    ExternalLink,
    FileBarChart2,
    FileText,
    FlaskConical,
    Footprints,
    GitBranch,
    Globe,
    Grid,
    HandCoins,
    IdCard,
    LayoutDashboard,
    LayoutGrid,
    ListOrdered,
    Loader2,
    LogIn,
    LockKeyhole,
    LogOut,
    Menu,
    MoreHorizontal,
    Moon,
    Package,
    PackageCheck,
    PackageOpen,
    Plus,
    Printer,
    QrCode,
    ReceiptText,
    RefreshCw,
    Save,
    Search,
    Settings,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Shirt,
    Sparkles,
    Scale,
    Star,
    Store,
    Sun,
    Tag,
    Tags,
    Timer,
    TriangleAlert,
    Trash2,
    Truck,
    UserRound,
    Users,
    Wallet,
    WashingMachine,
    Wind,
    X,
    Zap,
} from 'lucide-static';

window.Alpine = Alpine;
window.Swal = Swal;
window.flatpickr = flatpickr;
window.Chart = Chart;
window.toast = Swal.mixin({
    toast: true,
    position: 'bottom-end',
    showConfirmButton: false,
    timer: 5200,
    timerProgressBar: true,
    showClass: {
        popup: 'swal2-show',
        backdrop: 'swal2-noanimation',
    },
    hideClass: {
        popup: 'swal2-hide',
        backdrop: 'swal2-noanimation',
    },
});

const icons = {
    activity: Activity,
    arrowLeft: ArrowLeft,
    'arrow-left': ArrowLeft,
    bell: Bell,
    bot: Bot,
    building: Building2,
    calendar: CalendarCheck,
    check: Check,
    chevronDown: ChevronDown,
    cycles: Activity,
    dashboard: LayoutDashboard,
    dollar: CircleDollarSign,
    expense: CircleDollarSign,
    eye: Eye,
    eyeOff: EyeOff,
    externalLink: ExternalLink,
    'external-link': ExternalLink,
    flaskConical: FlaskConical,
    'flask-conical': FlaskConical,
    footprints: Footprints,
    gitBranch: GitBranch,
    'git-branch': GitBranch,
    globe: Globe,
    grid: Grid,
    inventory: Package,
    fileText: FileText,
    'file-text': FileText,
    jobOrders: ClipboardList,
    laundry: WashingMachine,
    layoutGrid: LayoutGrid,
    'layout-grid': LayoutGrid,
    listOrdered: ListOrdered,
    'list-ordered': ListOrdered,
    loader: Loader2,
    lock: LockKeyhole,
    login: LogIn,
    logout: LogOut,
    menu: Menu,
    moreHorizontal: MoreHorizontal,
    'more-horizontal': MoreHorizontal,
    moon: Moon,
    package: Package,
    packageCheck: PackageCheck,
    'package-check': PackageCheck,
    packageOpen: PackageOpen,
    'package-open': PackageOpen,
    payments: CreditCard,
    plus: Plus,
    printer: Printer,
    qr: QrCode,
    receipt: ReceiptText,
    receivables: HandCoins,
    refreshCw: RefreshCw,
    'refresh-cw': RefreshCw,
    reports: FileBarChart2,
    save: Save,
    search: Search,
    services: Tags,
    settings: Settings,
    shieldCheck: ShieldCheck,
    shoppingBag: ShoppingBag,
    'shopping-bag': ShoppingBag,
    shoppingCart: ShoppingCart,
    'shopping-cart': ShoppingCart,
    shirt: Shirt,
    sms: Bell,
    sparkles: Sparkles,
    scale: Scale,
    star: Star,
    store: Store,
    sun: Sun,
    tag: Tag,
    timer: Timer,
    alertTriangle: TriangleAlert,
    truck: Truck,
    trash: Trash2,
    user: UserRound,
    users: Users,
    wallet: Wallet,
    wind: Wind,
    x: X,
    zap: Zap,
    branches: Building2,
    customers: Users,
    attendance: CalendarCheck,
    employees: IdCard,
    smsLogs: Bell,
};

window.renderLucideIcons = () => {
    document.querySelectorAll('[data-lucide]').forEach((node) => {
        const name = node.dataset.lucide;
        const svg = icons[name];

        if (!svg) {
            return;
        }

        const wrapper = document.createElement('span');
        wrapper.innerHTML = svg.trim();
        const icon = wrapper.firstElementChild;

        Array.from(node.attributes).forEach((attribute) => {
            if (attribute.name !== 'data-lucide') {
                icon.setAttribute(attribute.name, attribute.value);
            }
        });

        icon.setAttribute('class', node.getAttribute('class') || 'h-5 w-5');
        icon.setAttribute('aria-hidden', 'true');
        node.replaceWith(icon);
    });
};

const appThemeDefault = Boolean(window.appDarkModeDefault);
const appThemeDefaultKey = String(appThemeDefault);
const storedThemeDefaultKey = localStorage.getItem('themeDefault');
const storedTheme = localStorage.getItem('theme');

Alpine.store('theme', {
    dark: storedTheme && storedThemeDefaultKey === appThemeDefaultKey
        ? storedTheme === 'dark'
        : appThemeDefault,

    init() {
        this.apply();
    },

    toggle() {
        this.dark = !this.dark;
        localStorage.setItem('theme', this.dark ? 'dark' : 'light');
        localStorage.setItem('themeDefault', appThemeDefaultKey);
        this.apply();
    },

    apply() {
        localStorage.setItem('themeDefault', appThemeDefaultKey);
        document.documentElement.classList.toggle('dark', this.dark);
        document.documentElement.style.colorScheme = this.dark ? 'dark' : 'light';
    }
});

Alpine.start();

document.addEventListener('DOMContentLoaded', window.renderLucideIcons);
document.addEventListener('alpine:init', () => {
    queueMicrotask(window.renderLucideIcons);
});
