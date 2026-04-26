<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$user = $_SESSION['user'] ?? [];
$profilePic = !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuBywp6azeuj_nfhEVWrzsoLDNZjla3ZGmVIKTDpfGTHgWK9hRlkLn8j5oTwCJXU0KgOVo9n2zH610GWaoZujrKBkHtcjokrSj4_TTwwL0754ynndRwOsYNOQnkBsiBzzXllT-ZMQkOqAP6cvqf3KpIkWn1ubfL9wAKpwuTFQwK71Bnd5UcqArtWFMs0IcREIdPfO-dfZOBAMdHReZEFm8iFozZV28-TUO52-cJZa9X_HmqvrtgCBdlDicpUzvyIg1uH6uisyOITPDA';
$displayName = htmlspecialchars(!empty($user['full_name']) ? $user['full_name'] : ($user['username'] ?? 'User'));
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mobility Programs | Central Philippine University</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "on-surface-variant": "#4a4455",
              "surface-container-highest": "#d3e4fe",
              "surface-container": "#d3e4fe",
              "surface-container-low": "#eff4ff",
              "on-primary-fixed": "#25005a",
              "on-primary": "#ffffff",
              "on-secondary-fixed-variant": "#004c6e",
              "on-secondary-fixed": "#001e2f",
              "surface-tint": "#732ee4",
              "primary-fixed": "#eaddff",
              "on-surface": "#0b1c30",
              "inverse-primary": "#d2bbff",
              "surface-container-high": "#dce9ff",
              "secondary-container": "#39b8fd",
              "on-primary-container": "#ede0ff",
              "secondary-fixed": "#c9e6ff",
              "on-secondary": "#ffffff",
              "secondary": "#006591",
              "on-tertiary-container": "#ffdedf",
              "surface-bright": "#f8f9ff",
              "on-tertiary-fixed": "#40000d",
              "on-background": "#0b1c30",
              "on-error-container": "#93000a",
              "error-container": "#ffdad6",
              "secondary-fixed-dim": "#89ceff",
              "surface-variant": "#d3e4fe",
              primary: "#630ed4",
              "background": "#f8f9ff",
              "outline-variant": "#ccc3d8",
              "on-error": "#ffffff",
              "surface-dim": "#cbdbf5",
              "tertiary-container": "#c81a42",
              "primary-container": "#7c3aed",
              "on-secondary-container": "#004666",
              error: "#ba1a1a",
              outline: "#7b7487",
              "on-tertiary-fixed-variant": "#92002a",
              "on-primary-fixed-variant": "#5a00c6",
              "tertiary-fixed-dim": "#ffb2b7",
              surface: "#f8f9ff",
              "inverse-surface": "#213145",
              tertiary: "#a0002f",
              "inverse-on-surface": "#eaf1ff",
              "surface-container-lowest": "#ffffff",
              "on-tertiary": "#ffffff",
              "primary-fixed-dim": "#d2bbff",
              "card-light": "#fafbfc",
              "card-dark": "#1e293b",
              "text-light": "#1e293b",
              "text-dark": "#e2e8f0",
              "text-muted-light": "#64748b",
              "text-muted-dark": "#94a3b8",
              "border-light": "#d1d5db",
              "border-dark": "#334155",
              "background-light": "#e8ecf1",
              "background-dark": "#0f172a",
            },
            fontFamily: {
              headline: ["Inter", "sans-serif"],
              body: ["Inter", "sans-serif"],
              label: ["Inter", "sans-serif"],
              display: ["Inter", "sans-serif"],
            },
            borderRadius: { DEFAULT: "0.125rem", lg: "0.25rem", xl: "0.5rem", full: "0.75rem" },
          },
        },
      };
    </script>
    <style>
        body { font-family: Inter, sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .material-symbols-outlined.filled { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #dce9ff; border-radius: 10px; }
        .sidebar-lilac { width: 16rem; min-width: 16rem; max-width: 16rem; }
        /* KPI cards: not interactive — remove focus ring / tap flash / stray caret line on click */
        .mobility-kpi-card {
            -webkit-tap-highlight-color: transparent;
            outline: none;
            user-select: none;
        }
        .mobility-kpi-card:focus,
        .mobility-kpi-card:focus-visible,
        .mobility-kpi-card *:focus,
        .mobility-kpi-card *:focus-visible {
            outline: none !important;
            box-shadow: none;
        }
        .mobility-kpi-card .material-symbols-outlined {
            user-select: none;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-surface text-on-surface flex min-h-screen overflow-x-hidden">
    <!-- LILAC app sidebar (structure unchanged from your app; tokens merged into Tailwind config above) -->
    <aside class="sidebar-lilac bg-card-light dark:bg-card-dark border-r border-border-light dark:border-border-dark flex flex-col fixed h-full z-50">
        <div class="flex items-center justify-start px-4 h-20 border-b border-border-light dark:border-border-dark flex-shrink-0">
            <div class="flex items-center gap-3 overflow-hidden">
                <img alt="CPU LILAC Logo" class="h-11 w-11 flex-shrink-0" src="./api/get-logo.php?v=1" width="44" height="44" onerror="this.style.display='none'; document.getElementById('logo-fallback-mp').style.display='flex';" />
                <div class="h-11 w-11 bg-primary rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0 hidden" id="logo-fallback-mp">CPU</div>
                <h1 class="text-xl font-bold text-text-light dark:text-text-dark whitespace-nowrap">LILAC</h1>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="dashboard.php"><span class="material-symbols-outlined flex-shrink-0">dashboard</span><span class="whitespace-nowrap">Dashboard</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="awards-hub.php"><span class="material-symbols-outlined flex-shrink-0">military_tech</span><span class="whitespace-nowrap">ICONS 2025</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="awards.php"><span class="material-symbols-outlined flex-shrink-0">emoji_events</span><span class="whitespace-nowrap">Awards Progress</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/40 dark:to-indigo-900/40 text-purple-600 dark:text-purple-400 font-semibold border border-purple-200 dark:border-purple-800 shadow-sm" href="mobility-programs.php"><span class="material-symbols-outlined filled flex-shrink-0">map</span><span class="whitespace-nowrap">Mobility Programs</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="events-activities.php"><span class="material-symbols-outlined flex-shrink-0">event</span><span class="whitespace-nowrap">Events &amp; Activities</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="scheduler.php"><span class="material-symbols-outlined flex-shrink-0">calendar_today</span><span class="whitespace-nowrap">Scheduler</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="mou-moa.php"><span class="material-symbols-outlined flex-shrink-0">handshake</span><span class="whitespace-nowrap">MOUs &amp; MOAs</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="documents.php"><span class="material-symbols-outlined flex-shrink-0">description</span><span class="whitespace-nowrap">Documents</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="forms.php"><span class="material-symbols-outlined flex-shrink-0">edit_note</span><span class="whitespace-nowrap">Forms</span></a>
            <a class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-text-muted-light dark:text-text-muted-dark hover:bg-purple-50 dark:hover:bg-purple-900/20 hover:text-purple-600 dark:hover:text-purple-400 transition-colors duration-200" href="trash.php"><span class="material-symbols-outlined flex-shrink-0">delete</span><span class="whitespace-nowrap">Trash</span></a>
        </nav>
    </aside>

    <!-- SRC layout: main column (matches your provided HTML; ml-64 for LILAC sidebar) -->
    <main class="ml-64 flex-1 min-h-screen overflow-y-auto scroll-smooth bg-surface">
        <header class="w-full top-0 sticky z-40 bg-[#f8f9ff] dark:bg-slate-950 flex justify-between items-center px-8 h-16 transition-all border-b border-surface-container-low/60">
            <div class="flex items-center gap-4">
                <h2 class="text-xl font-bold text-[#0b1c30] dark:text-slate-100 tracking-tight">Mobility Programs</h2>
            </div>
            <div class="flex items-center gap-6">
                <div class="relative group">
                    <input class="bg-surface-container-low border-none rounded-full px-4 py-1.5 text-sm w-64 focus:ring-2 focus:ring-primary transition-all text-on-surface placeholder:text-slate-400" placeholder="Search data points..." type="text" />
                    <span class="material-symbols-outlined absolute right-3 top-1.5 text-slate-400 text-xl pointer-events-none">search</span>
                </div>
                <div class="flex items-center gap-4 text-slate-500">
                    <span class="material-symbols-outlined cursor-pointer hover:text-primary transition-colors">calendar_month</span>
                    <span class="material-symbols-outlined cursor-pointer hover:text-primary transition-colors">notifications</span>
                    <span class="material-symbols-outlined cursor-pointer hover:text-primary transition-colors">settings</span>
                    <div class="h-8 w-8 rounded-full bg-surface-container-highest overflow-hidden border-2 border-surface-container flex items-center justify-center" title="<?php echo $displayName; ?>">
                        <img alt="User Profile" class="w-full h-full object-cover" src="<?php echo $profilePic; ?>" />
                    </div>
                </div>
            </div>
        </header>

        <div class="p-8 max-w-[1600px] mx-auto space-y-8 pb-24">
            <section>
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h3 class="text-2xl font-bold tracking-tight text-on-surface">Executive Summary</h3>
                        <p class="text-on-surface-variant text-sm mt-1">Real-time indicators across institutional, faculty, and student domains.</p>
                    </div>
                    <div class="text-xs font-bold px-3 py-1 bg-surface-container rounded-full text-primary">FY 2019-2025 DATASET</div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-primary opacity-60 uppercase tracking-tighter">Institutional</span>
                            <span class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">military_tech</span>
                        </div>
                        <div class="mt-4">
                            <div id="kpi-international-awards" class="text-3xl font-black text-on-surface leading-none">0</div>
                            <div class="text-xs font-bold text-on-surface-variant mt-2">List of International Awards</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-primary opacity-60 uppercase tracking-tighter">Partnerships</span>
                            <span class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">groups</span>
                        </div>
                        <div class="mt-4">
                            <div id="kpi-active-partnerships" class="text-3xl font-black text-on-surface leading-none">0</div>
                            <div class="text-xs font-bold text-on-surface-variant mt-2">Active International Partnerships</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-primary opacity-60 uppercase tracking-tighter">Student</span>
                            <span class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">swap_horiz</span>
                        </div>
                        <div class="mt-4">
                            <div id="kpi-outgoing-exchange-students" class="text-3xl font-black text-on-surface leading-none">0</div>
                            <div class="text-xs font-bold text-on-surface-variant mt-2">Outgoing Exchange Students</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-primary opacity-60 uppercase tracking-tighter">Student</span>
                            <span class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">flight_land</span>
                        </div>
                        <div class="mt-4">
                            <div id="kpi-incoming-exchange-students" class="text-3xl font-black text-on-surface leading-none">0</div>
                            <div class="text-xs font-bold text-on-surface-variant mt-2">Incoming Exchange Students</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="flex justify-between items-start">
                            <span class="text-xs font-bold text-primary opacity-60 uppercase tracking-tighter">Faculty</span>
                            <span class="material-symbols-outlined text-primary group-hover:scale-110 transition-transform">person_celebrate</span>
                        </div>
                        <div class="mt-4">
                            <div id="kpi-international-faculty-experts" class="text-3xl font-black text-on-surface leading-none">0</div>
                            <div class="text-xs font-bold text-on-surface-variant mt-2">International Faculty Experts</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-primary-container p-5 rounded-xl shadow-lg flex flex-col justify-between text-white relative overflow-hidden outline-none">
                        <div class="absolute -right-2 -top-2 opacity-10">
                            <span class="material-symbols-outlined text-8xl filled">public</span>
                        </div>
                        <div class="flex justify-between items-start z-10">
                            <span class="text-xs font-bold opacity-80 uppercase tracking-tighter">Global Impact</span>
                            <span class="material-symbols-outlined filled">stars</span>
                        </div>
                        <div class="mt-4 z-10">
                            <div id="kpi-internationalization-target-met" class="text-3xl font-black leading-none">0%</div>
                            <div class="text-xs font-bold opacity-90 mt-2">Internationalization Target Met</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="mt-auto">
                            <div id="kpi-joint-degree-programs" class="text-xl font-bold text-on-surface">0</div>
                            <div class="text-[10px] font-medium text-on-surface-variant mt-1 uppercase">Joint Degree Programs</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="mt-auto">
                            <div id="kpi-transnational-centers" class="text-xl font-bold text-on-surface">0</div>
                            <div class="text-[10px] font-medium text-on-surface-variant mt-1 uppercase">Transnational Centers</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="mt-auto">
                            <div id="kpi-international-internships" class="text-xl font-bold text-on-surface">0</div>
                            <div class="text-[10px] font-medium text-on-surface-variant mt-1 uppercase">International Internships</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="mt-auto">
                            <div id="kpi-research-grants" class="text-xl font-bold text-on-surface">0</div>
                            <div class="text-[10px] font-medium text-on-surface-variant mt-1 uppercase">Research Grants</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="mt-auto">
                            <div id="kpi-scholarship-slots" class="text-xl font-bold text-on-surface">0</div>
                            <div class="text-[10px] font-medium text-on-surface-variant mt-1 uppercase">Scholarship Slots</div>
                        </div>
                    </div>
                    <div class="mobility-kpi-card bg-surface-container-lowest p-5 rounded-xl border border-surface-container-low flex flex-col justify-between hover:shadow-lg transition-all cursor-default group outline-none">
                        <div class="mt-auto">
                            <div id="kpi-asean-event-attendees" class="text-xl font-bold text-on-surface">0</div>
                            <div class="text-[10px] font-medium text-on-surface-variant mt-1 uppercase">ASEAN Event Attendees</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="mobility-tabs-panels-section" class="space-y-4" style="display:none;">
                <div class="overflow-x-auto pb-2 -mx-2">
                    <div class="flex gap-2 px-2 min-w-max" id="mobility-tab-buttons" role="tablist">
                        <button type="button" role="tab" data-mobility-tab="awards" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container flex items-center gap-2">List of International Awards</button>
                        <button type="button" role="tab" data-mobility-tab="memberships" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Institutional Memberships</button>
                        <button type="button" role="tab" data-mobility-tab="linkages" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Linkages and Partnerships</button>
                        <button type="button" role="tab" data-mobility-tab="student-mobility" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Student Mobility and Internships</button>
                        <button type="button" role="tab" data-mobility-tab="scholarships" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">International Scholarships and Fellowships</button>
                        <button type="button" role="tab" data-mobility-tab="staff-mobility" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Staff Mobility and Scholarships</button>
                        <button type="button" role="tab" data-mobility-tab="full-time-foreign-students" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Full Time foreign student</button>
                        <button type="button" role="tab" data-mobility-tab="full-time-foreign-faculty" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Full Time foreign Faculty</button>
                        <button type="button" role="tab" data-mobility-tab="internationalization-research" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Internationalization of Research</button>
                        <button type="button" role="tab" data-mobility-tab="coil-classes" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Collaborative Online International Learning (COIL) Classes</button>
                        <button type="button" role="tab" data-mobility-tab="transnational-education-program" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Transnational Education Programs</button>
                        <button type="button" role="tab" data-mobility-tab="collaborative-events-activities" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">Collaborative Events and Activities</button>
                        <button type="button" role="tab" data-mobility-tab="in-house-asean-internationalization-events" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">In-house ASEAN and Internationalization Events</button>
                        <button type="button" role="tab" data-mobility-tab="international-sustainability-centers" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">International/Sustainability Centers (e.g. ASEAN, SEAMEO, UNESCO Centers based in the HEI)</button>
                        <button type="button" role="tab" data-mobility-tab="studyph-program" aria-selected="false" class="mobility-tab bg-surface-container-high text-on-surface-variant px-5 py-2.5 rounded-full text-xs font-bold transition-all hover:bg-surface-container">StudyPH Program</button>
                    </div>
                </div>

                <div id="panel-awards" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6" role="tabpanel">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-8">
                        <div>
                            <h4 class="text-xl font-bold text-on-surface">List of International Awards</h4>
                            <p class="text-on-surface-variant text-sm">Comprehensive performance tracking and award metrics by year.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" id="btn-add-award-record" class="bg-tertiary text-on-tertiary px-5 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                            <button type="button" class="bg-surface-container-highest text-secondary px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 hover:bg-surface-container transition-all">
                                <span class="material-symbols-outlined text-lg">download</span>
                                Export to Excel
                            </button>
                        </div>
                    </div>

                    <div class="overflow-hidden bg-surface-container-lowest rounded-xl border border-outline-variant/10 shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-surface-container text-on-surface border-b border-surface-container">
                                    <th class="px-6 py-5 text-sm font-bold tracking-tight">Mobility Indicator</th>
                                    <th class="px-4 py-5 text-sm font-bold text-center">2019</th>
                                    <th class="px-4 py-5 text-sm font-bold text-center">2020</th>
                                    <th class="px-4 py-5 text-sm font-bold text-center">2021</th>
                                    <th class="px-4 py-5 text-sm font-bold text-center">2022</th>
                                    <th class="px-4 py-5 text-sm font-bold text-center">2023</th>
                                    <th class="px-4 py-5 text-sm font-bold text-center">2024</th>
                                    <th class="px-4 py-5 text-sm font-bold text-center">2025</th>
                                </tr>
                            </thead>
                            <tbody id="awards-table-body" class="divide-y divide-surface-container-low">
                                <tr id="awards-loading-row">
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-on-surface-variant">Loading awards records...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>

                <div id="panel-memberships" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Institutional Memberships</h3>
                        <div class="flex flex-wrap gap-2">
                            <div class="bg-surface-container-lowest px-4 py-2 rounded-full flex items-center gap-2 border border-outline-variant/20">
                                <span class="material-symbols-outlined text-sm text-on-surface-variant">search</span>
                                <input class="bg-transparent border-none text-sm focus:ring-0 p-0 w-40 text-on-surface placeholder:text-on-surface-variant/40" placeholder="Search" type="search" id="membership-filter" autocomplete="off" />
                            </div>
                            <button type="button" id="membership-filter-btn" class="bg-surface-container-lowest px-4 py-2 rounded-full text-sm font-medium hover:bg-surface-container transition-colors border border-outline-variant/20">
                                Filter
                            </button>
                            <button type="button" id="membership-bulk-delete-btn" class="hidden bg-surface-container-highest text-on-surface-variant px-4 py-2 rounded-full text-sm font-bold hover:bg-surface-container transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                Delete
                            </button>
                            <button type="button" id="btn-add-membership-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">Add New Record</button>
                        </div>
                    </div>

                    <div id="membership-filter-panel" class="hidden mb-8 rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="membership-filter-type">Type</label>
                                <select id="membership-filter-type" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option value="ALL" selected>All</option>
                                    <option value="INTERNATIONAL">International</option>
                                    <option value="LOCAL">Local</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="membership-filter-status">Status</label>
                                <select id="membership-filter-status" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option value="ALL" selected>All</option>
                                    <option value="ANNUAL">Annual</option>
                                    <option value="LIFETIME">Lifetime</option>
                                    <option value="AUTONOMOUS">Autonomous</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-4">
                            <button type="button" id="membership-filter-clear" class="px-4 py-2 rounded-lg text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors">
                                Clear
                            </button>
                            <button type="button" id="membership-filter-apply" class="px-5 py-2 rounded-lg text-xs font-bold bg-primary text-on-primary hover:opacity-95 transition-colors">
                                Apply
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[640px]">
                            <thead>
                                <tr class="text-on-surface border-b border-outline-variant/10">
                                    <th class="py-4 w-12 px-0 align-middle">
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" id="membership-select-all" class="block m-0 p-0 w-4 h-4 rounded border-outline-variant/20 text-primary focus:ring-primary/30" aria-label="Select all membership rows" />
                                        </div>
                                    </th>
                                    <th class="py-4 align-middle font-bold text-xs uppercase tracking-wider">Organization Name</th>
                                    <th class="py-4 align-middle font-bold text-xs uppercase tracking-wider">Type</th>
                                    <th class="py-4 align-middle font-bold text-xs uppercase tracking-wider">Status</th>
                                    <th class="py-4 align-middle font-bold text-xs uppercase tracking-wider">Validity</th>
                                    <th class="py-4 align-middle"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-transparent" id="membership-table-body">
                                <tr id="membership-loading-row">
                                    <td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">
                                        Loading…
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="panel-linkages" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Linkages and Partnerships</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-linkage-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1100px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner Institution</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Type of Partner<br><span class="normal-case font-medium tracking-normal text-[11px] text-on-surface-variant">*e.g. private org, foreign HEI, etc</span></th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Country</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Type of Agreement<br><span class="normal-case font-medium tracking-normal text-[11px] text-on-surface-variant">*e.g. MOA, MOU, etc.</span></th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Field</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Date Signed</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partnership End Date</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">UN SDGs Covered<br><span class="normal-case font-medium tracking-normal text-[11px] text-on-surface-variant">*Please separate by comma</span></th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="linkages-table-body">
                                    <tr id="linkages-loading-row">
                                        <td colspan="9" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-student-mobility" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Student Mobility and Internships</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-student-mobility-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1000px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner Institution</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Country</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Scope</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Activity Modality</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Duration</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Program</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Starting Year</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">End Year</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="student-mobility-table-body">
                                    <tr>
                                        <td colspan="9" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-scholarships" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">International Scholarships and Fellowships</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-scholarship-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1150px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner Institution</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Country</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Scope</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Type of Program/Activity</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Field of Specialization</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Faculty/Staff Count</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Modality</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Starting Year</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">End Year</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="scholarships-table-body">
                                    <tr>
                                        <td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-staff-mobility" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Staff Mobility and Scholarships</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-staff-mobility-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1000px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner Institution</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Country</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Scope</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Type of Program Activity</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Modality</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Starting Year</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">End Year</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="staff-mobility-table-body">
                                    <tr>
                                        <td colspan="8" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-full-time-foreign-students" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Full-Time Foreign Students</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-full-time-foreign-student-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[700px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Student Category</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider text-center">Total Students</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider text-center">Records</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="full-time-students-table-body">
                                    <tr>
                                        <td colspan="3" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-full-time-foreign-faculty" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Full-Time Foreign Faculty</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-full-time-foreign-faculty-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[700px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Teaching Category</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider text-center">Total Faculty</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider text-center">Records</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="full-time-faculty-table-body">
                                    <tr>
                                        <td colspan="3" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-internationalization-research" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Internationalization of Research</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-internationalization-research-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1200px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Category</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Faculty Name</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Fiscal Year</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Research Title / Topic</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner / Journal</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner Country</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Project Status</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Published</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">SDG Focus</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="research-table-body">
                                    <tr>
                                        <td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-coil-classes" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">COIL Classes</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-coil-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[850px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner University</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Country</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">COIL Subject</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Year</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="coil-table-body">
                                    <tr>
                                        <td colspan="5" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-transnational-education-program" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Transnational Education Programs</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-transnational-education-program-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1100px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partner University</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Country</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Academic Program</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">CHED Permit/Approval</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Students</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Nationalities</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Year Started</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="tn-table-body">
                                    <tr>
                                        <td colspan="8" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-collaborative-events-activities" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">Collaborative Events and Activities</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-collaborative-events-activities-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1000px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Collaborative Event / Activity</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partners</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Countries</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Participants</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Year</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="cea-table-body">
                                    <tr>
                                        <td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-in-house-asean-internationalization-events" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">In-house ASEAN and Internationalization Events</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-in-house-asean-internationalization-event-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1000px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">ASEAN / Internationalization Event</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partners</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Countries</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Participants</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Year</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="inhouse-table-body">
                                    <tr>
                                        <td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-international-sustainability-centers" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">International/Sustainability Centers</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-international-sustainability-center-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[950px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">International Center</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Partners</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Countries</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Year Established</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="isc-table-body">
                                    <tr>
                                        <td colspan="5" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-studyph-program" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-6 sm:p-8 shadow-sm" role="tabpanel">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                        <h3 class="text-xl font-bold text-on-surface">StudyPH Program</h3>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" id="btn-add-studyph-program-record" class="bg-tertiary text-on-tertiary px-4 py-2 rounded-full text-sm font-medium hover:opacity-90 transition-opacity">
                                Add New Record
                            </button>
                        </div>
                    </div>
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[1400px]">
                                <thead>
                                    <tr class="text-on-surface border-b border-outline-variant/10">
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Year</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">KRA</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Project Title</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Field/Area</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">UN SDG Covered</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Amount (PHP)</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">Beneficiaries</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">KPI</th>
                                        <th class="py-3 px-4 align-middle font-bold text-xs uppercase tracking-wider">KPI Value</th>
                                        <th class="py-3 px-4 align-middle"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-transparent" id="studyph-table-body">
                                    <tr>
                                        <td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-other" class="mobility-tab-panel hidden bg-surface-container-low rounded-2xl p-12 text-center" role="tabpanel">
                    <p class="text-on-surface-variant max-w-lg mx-auto">This section is under curation. Use <strong class="text-on-surface">List of International Awards</strong> or <strong class="text-on-surface">Institutional Memberships</strong> for full records.</p>
                </div>
            </section>
        </div>

        <footer class="mt-auto px-8 py-6 border-t border-surface-container text-center bg-surface">
            <p class="text-xs text-on-surface-variant font-medium opacity-60">
                © 2024 Central Philippine University Mobility Systems. All rights reserved.
                <span class="mx-2">|</span>
                Internal Use Only
            </p>
        </footer>
    </main>

    <!-- Floating add button removed per request -->

    <!-- International Awards add modal -->
    <div id="awards-add-modal" class="hidden fixed inset-0 z-[101] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="awards-add-title">
        <div id="awards-add-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-2xl bg-surface rounded-2xl shadow-2xl border border-outline-variant/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant/10 flex items-start justify-between gap-4">
                <div>
                    <h3 id="awards-add-title" class="text-lg font-black tracking-tight text-on-surface">Add International Award Record</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Add one row that will appear in this awards table and detail modal.</p>
                </div>
                <button type="button" id="awards-add-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
            </div>
            <div class="px-5 pt-4 pb-2">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2" role="tablist" aria-label="International awards categories">
                    <button type="button" id="awards-add-tab-institutional" data-awards-add-tab="institutional" class="awards-add-tab-btn px-3 py-2 rounded-lg text-xs font-bold bg-primary text-on-primary">International Institutional Awards</button>
                    <button type="button" id="awards-add-tab-accreditation" data-awards-add-tab="accreditation" class="awards-add-tab-btn px-3 py-2 rounded-lg text-xs font-bold bg-surface-container text-on-surface-variant hover:bg-surface-container-high">International Accreditations, Assessments and Rating</button>
                    <button type="button" id="awards-add-tab-ranking" data-awards-add-tab="ranking" class="awards-add-tab-btn px-3 py-2 rounded-lg text-xs font-bold bg-surface-container text-on-surface-variant hover:bg-surface-container-high">International Rankings and Ratings</button>
                </div>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div id="awards-add-panel-institutional" class="awards-add-tab-panel grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="awards-add-inst-name" class="block text-xs font-bold text-on-surface mb-1">Name of Award</label>
                        <input id="awards-add-inst-name" type="text" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="Enter award name" />
                    </div>
                    <div>
                        <label for="awards-add-inst-body" class="block text-xs font-bold text-on-surface mb-1">Award Giving Body</label>
                        <input id="awards-add-inst-body" type="text" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="Enter giving body" />
                    </div>
                    <div>
                        <label for="awards-add-inst-year" class="block text-xs font-bold text-on-surface mb-1">Year Awarded</label>
                        <input id="awards-add-inst-year" type="text" inputmode="numeric" maxlength="4" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="e.g. 2025" />
                    </div>
                </div>

                <div id="awards-add-panel-accreditation" class="awards-add-tab-panel hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="awards-add-accred-name" class="block text-xs font-bold text-on-surface mb-1">Name of Accreditation/ Assessment</label>
                        <input id="awards-add-accred-name" type="text" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="Enter accreditation/assessment name" />
                    </div>
                    <div>
                        <label for="awards-add-accred-body" class="block text-xs font-bold text-on-surface mb-1">Assessment/ Accrediting Body</label>
                        <input id="awards-add-accred-body" type="text" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="Enter accrediting body" />
                    </div>
                    <div>
                        <label for="awards-add-accred-year" class="block text-xs font-bold text-on-surface mb-1">Year</label>
                        <input id="awards-add-accred-year" type="text" inputmode="numeric" maxlength="4" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="e.g. 2019" />
                    </div>
                </div>

                <div id="awards-add-panel-ranking" class="awards-add-tab-panel hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label for="awards-add-ranking-name" class="block text-xs font-bold text-on-surface mb-1">Name of International Ranking/ Rating</label>
                        <input id="awards-add-ranking-name" type="text" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="Enter ranking/rating name" />
                    </div>
                    <div>
                        <label for="awards-add-ranking-rank" class="block text-xs font-bold text-on-surface mb-1">Rank</label>
                        <input id="awards-add-ranking-rank" type="text" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="e.g. 46th" />
                    </div>
                    <div>
                        <label for="awards-add-ranking-year" class="block text-xs font-bold text-on-surface mb-1">Year</label>
                        <input id="awards-add-ranking-year" type="text" inputmode="numeric" maxlength="4" class="w-full rounded-lg bg-surface-container-low border-0 px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary/30" placeholder="e.g. 2025" />
                    </div>
                </div>
            </div>
            <div class="px-5 py-4 border-t border-outline-variant/10 flex justify-end gap-2">
                <button type="button" id="awards-add-modal-cancel" class="px-4 py-2 rounded-lg text-sm font-semibold bg-surface-container text-on-surface-variant hover:bg-surface-container-high">Cancel</button>
                <button type="button" id="awards-add-modal-save" class="px-4 py-2 rounded-lg text-sm font-semibold bg-primary text-on-primary hover:opacity-90">Save Record</button>
            </div>
        </div>
    </div>

    <!-- International Awards detail modal -->
    <div id="awards-detail-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="awards-detail-title">
        <div id="awards-detail-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative w-full max-w-6xl rounded-2xl overflow-hidden shadow-2xl border border-outline-variant/10 bg-surface">
            <div class="px-5 py-4 border-b border-outline-variant/10 flex items-start justify-between gap-4">
                <div>
                    <h3 id="awards-detail-title" class="text-lg font-black tracking-tight text-on-surface">Award Detail</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Existing excel-based records for this indicator.</p>
                </div>
                <button type="button" id="awards-detail-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4">
                    <div class="text-[11px] uppercase tracking-wider font-bold text-on-surface-variant mb-2">Current Files / Data</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[640px]">
                            <thead>
                                <tr class="border-b border-outline-variant/10 text-on-surface">
                                    <th class="py-2 px-2 text-xs font-bold">Title</th>
                                    <th class="py-2 px-2 text-xs font-bold">Source / Rank</th>
                                    <th class="py-2 px-2 text-xs font-bold">Year</th>
                                    <th class="py-2 px-2 text-xs font-bold">Action</th>
                                </tr>
                            </thead>
                            <tbody id="awards-detail-files-body">
                                <tr>
                                    <td colspan="4" class="py-4 px-2 text-sm text-on-surface-variant">Loading records…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-4">
                    <p class="text-xs text-on-surface-variant">Source: CHED Internationalization Report.xlsx - sheet "3. List of International Awards".</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Institutional Membership Record modal (SRC) -->
    <div id="membership-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="membership-modal-title">
        <div id="membership-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden">
            <div class="bg-gradient-to-r from-primary to-primary-container px-5 py-4 text-on-primary">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase">Member Data Entry</span>
                    <button type="button" id="membership-modal-close" class="material-symbols-outlined text-lg text-on-primary hover:bg-white/10 rounded-full p-0.5 transition-colors shrink-0" aria-label="Close dialog">close</button>
                </div>
                <h2 id="membership-modal-title" class="text-xl font-black leading-tight">Institutional Membership Record</h2>
                <p class="text-on-primary-container text-xs mt-1.5 opacity-90 leading-snug">Please ensure organization names match official registry records for data integrity.</p>
            </div>
            <div class="px-5 py-5 space-y-5">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-on-surface tracking-tight" for="org_name">Name of Organization</label>
                    <input class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 text-on-surface placeholder:text-on-surface-variant/40 transition-all" id="org_name" name="org_name" placeholder="e.g. World University Association" type="text" autocomplete="organization" />
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-on-surface tracking-tight">Classification</label>
                    <div class="flex bg-surface-container-low p-0.5 rounded-lg" role="group" aria-label="Classification">
                        <button type="button" id="classification-intl" class="classification-pill flex-1 py-1.5 text-xs font-bold rounded-md bg-primary text-on-primary shadow-sm" data-value="international">International</button>
                        <button type="button" id="classification-local" class="classification-pill flex-1 py-1.5 text-xs font-bold rounded-md text-on-surface-variant hover:bg-surface-container transition-colors" data-value="local">Local</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-on-surface tracking-tight" for="membership_year">Year</label>
                        <div class="relative">
                            <input class="w-full bg-surface-container-low border-none rounded-lg pl-3 pr-9 py-2 text-sm focus:ring-2 focus:ring-primary/30 text-on-surface transition-all" id="membership_year" name="membership_year" placeholder="e.g. 2024 or 2024-2029" type="text" inputmode="numeric" />
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-lg pointer-events-none text-on-surface-variant">calendar_today</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-on-surface tracking-tight" for="membership_status">Status</label>
                        <div class="relative">
                            <select class="w-full appearance-none bg-surface-container-low border-none rounded-lg pl-3 pr-9 py-2 text-sm focus:ring-2 focus:ring-primary/30 text-on-surface transition-all" id="membership_status" name="membership_status">
                                <option value="" selected>-- Select status --</option>
                                <option>Annual</option>
                                <option>Lifetime</option>
                                <option>Autonomous</option>
                            </select>
                        </div>
                        <input class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 text-on-surface placeholder:text-on-surface-variant/50 transition-all" id="membership_status_custom" name="membership_status_custom" placeholder="Or enter custom status" type="text" />
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 pt-1">
                    <button type="button" id="membership-modal-save" class="flex-1 bg-primary text-on-primary py-2.5 rounded-lg font-bold text-sm hover:shadow-lg hover:shadow-primary/20 active:scale-[0.98] transition-all flex items-center justify-center gap-1.5">
                        <span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">save</span>
                        Save
                    </button>
                    <button type="button" id="membership-modal-discard" class="sm:px-5 bg-surface-container-high text-on-surface-variant py-2.5 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">Discard</button>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Linkages & Partnerships modal (SRC - adapted) -->
    <div id="linkages-entry-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="linkages-entry-modal-title">
        <div id="linkages-entry-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="bg-gradient-to-r from-primary to-primary-container px-5 py-4 text-on-primary shrink-0">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase">Linkage Data Entry</span>
                    <button type="button" id="linkages-entry-modal-close" class="material-symbols-outlined text-lg text-on-primary hover:bg-white/10 rounded-full p-0.5 transition-colors shrink-0" aria-label="Close dialog">close</button>
                </div>
                <h2 id="linkages-entry-modal-title" class="text-xl font-black leading-tight">Linkages &amp; Partnerships</h2>
                <p class="text-on-primary-container text-xs mt-1.5 opacity-90 leading-snug">Create a new editorial record for mobility cooperation.</p>
                <div class="mt-4 flex bg-surface-container-low p-1 rounded-xl" role="group" aria-label="Partnership scope">
                    <button type="button" id="lp-tab-international" class="lp-tab-btn flex-1 py-2 text-xs font-bold rounded-lg bg-primary text-on-primary shadow-sm transition-colors" data-lp-tab="international">International</button>
                    <button type="button" id="lp-tab-local-industry" class="lp-tab-btn flex-1 py-2 text-xs font-bold rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container transition-colors" data-lp-tab="local-industry">Local Industry</button>
                    <button type="button" id="lp-tab-local-academe" class="lp-tab-btn flex-1 py-2 text-xs font-bold rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container transition-colors" data-lp-tab="local-academe">Local Academe</button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <form id="linkages-entry-form" class="space-y-4">
                    <div id="lp-panel-international" class="lp-tab-panel space-y-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="lp-partner-institution">Partner Institution</label>
                            <input id="lp-partner-institution" class="w-full px-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-on-surface placeholder:text-on-surface-variant/40 transition-all" placeholder="e.g. National University of Singapore" type="text"/>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="lp-type-of-partner">Type of Partner</label>
                                <div class="relative">
                                    <select id="lp-type-of-partner" class="w-full appearance-none px-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-on-surface transition-all cursor-pointer">
                                        <option value="">-- Select partner type --</option>
                                        <option value="Foreign HEI">Foreign HEI</option>
                                        <option value="Private Org">Private Org</option>
                                        <option value="Governmental Body">Governmental Body</option>
                                        <option value="NGO">NGO</option>
                                    </select>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">expand_more</span>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="lp-country">Country</label>
                                <div class="relative">
                                    <select id="lp-country" class="w-full appearance-none px-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-on-surface transition-all cursor-pointer">
                                        <option>Singapore</option>
                                        <option>United Kingdom</option>
                                        <option>Australia</option>
                                        <option>Japan</option>
                                        <option>Germany</option>
                                    </select>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">public</span>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="lp-type-of-agreement">Type of Agreement</label>
                                <div class="relative">
                                    <select id="lp-type-of-agreement" class="w-full appearance-none px-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-on-surface transition-all cursor-pointer" style="background-image:none; -webkit-appearance:none; -moz-appearance:none; appearance:none;">
                                        <option>MOU (Memorandum of Understanding)</option>
                                        <option>MOA (Memorandum of Agreement)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="lp-field">Field</label>
                                <input id="lp-field" class="w-full px-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-on-surface placeholder:text-on-surface-variant/40 transition-all" placeholder="e.g. Computer Science" type="text"/>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="lp-date-signed">Date Signed</label>
                                <div class="relative">
                                    <input id="lp-date-signed" class="w-full px-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-on-surface transition-all" type="date"/>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">calendar_today</span>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="lp-end-date">End Date</label>
                                <div class="relative">
                                    <input id="lp-end-date" class="w-full px-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-on-surface transition-all" type="date"/>
                                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm pointer-events-none">event_busy</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-1.5 p-3 bg-surface-container rounded-lg flex items-start gap-3">
                            <div class="p-2 bg-primary-container/10 rounded-lg shrink-0">
                                <span class="material-symbols-outlined text-primary" style="font-variation-settings:'FILL' 1;">info</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-on-surface">Data Verification Policy</p>
                                <p class="text-[11px] text-on-surface-variant leading-relaxed">International partnerships require supporting documentation to be uploaded after entry. Ensure all dates match the official signed documents.</p>
                            </div>
                        </div>
                    </div>

                    <div id="lp-panel-local-industry" class="lp-tab-panel hidden">
                        <p class="text-on-surface-variant text-sm">Local Industry entry form is coming soon.</p>
                    </div>
                    <div id="lp-panel-local-academe" class="lp-tab-panel hidden">
                        <p class="text-on-surface-variant text-sm">Local Academe entry form is coming soon.</p>
                    </div>
                </form>
            </div>

            <div class="px-5 py-3 shrink-0">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="lp-btn-discard" class="flex-1 bg-surface-container-high text-on-surface-variant py-2 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">
                        Discard Draft
                    </button>
                    <button type="button" id="lp-btn-create-entry" class="flex-1 bg-primary text-on-primary py-2 rounded-lg font-bold text-sm hover:shadow-xl hover:shadow-primary/20 active:scale-[0.98] transition-all">
                        Create Entry
                    </button>
                </div>
                <button type="button" id="lp-btn-save-draft" class="mt-2 w-full bg-surface-container-highest text-on-surface-variant py-2 rounded-lg font-bold text-sm hover:bg-surface-container transition-colors">
                    Save as Draft
                </button>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Student Mobility & Internship modal (SRC - adapted) -->
    <div id="student-mobility-entry-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="student-mobility-entry-modal-title">
        <div id="student-mobility-entry-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="bg-gradient-to-r from-primary to-primary-container px-5 py-4 text-on-primary shrink-0">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase">Student Mobility Entry</span>
                    <button type="button" id="student-mobility-entry-modal-close" class="material-symbols-outlined text-lg text-on-primary hover:bg-white/10 rounded-full p-0.5 transition-colors shrink-0" aria-label="Close dialog">close</button>
                </div>
                <h2 id="student-mobility-entry-modal-title" class="text-xl font-black leading-tight">Student Mobility &amp; Internship Record</h2>
                <p class="text-on-primary-container text-xs mt-1.5 opacity-90 leading-snug">Digital Curator • Institutional Data Entry</p>
                <div class="mt-4 flex bg-surface-container-low p-1 rounded-xl" role="group" aria-label="Scope">
                    <button type="button" id="student-scope-inbound" class="student-scope-btn flex-1 py-2 text-xs font-bold rounded-lg bg-primary text-on-primary shadow-sm transition-colors">Inbound</button>
                    <button type="button" id="student-scope-outbound" class="student-scope-btn flex-1 py-2 text-xs font-semibold rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">Outbound</button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <form id="student-mobility-entry-form" class="space-y-4">
                    <input type="hidden" id="student-scope-value" name="student_scope" value="inbound" />
                    <input type="hidden" id="student-duration-value" name="student_duration" value="short" />

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="student-partner-institution">Partner Institution</label>
                        <div class="relative">
                            <input id="student-partner-institution" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Search or enter institution name" type="text" />
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">apartment</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="student-country">Country</label>
                            <div class="relative">
                                <select id="student-country" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option disabled selected value="">Select country</option>
                                    <option>United Kingdom</option>
                                    <option>Germany</option>
                                    <option>Singapore</option>
                                    <option>Canada</option>
                                    <option>Australia</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">expand_more</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="student-activity-modality">Activity Modality</label>
                            <div class="relative">
                                <select id="student-activity-modality" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option>Physical</option>
                                    <option>Virtual</option>
                                    <option>Hybrid</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">sensors</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="student-duration">Duration</label>
                        <div class="flex gap-2">
                            <button type="button" id="student-duration-short" class="student-duration-btn flex-1 flex items-center justify-between p-3 rounded-xl border border-outline-variant/20 bg-surface-container-low hover:bg-surface-container transition-colors">
                                <span class="flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-primary"> 
                                        <span class="material-symbols-outlined text-[18px]">airport_shuttle</span>
                                    </span>
                                    <span class="font-bold text-on-surface text-xs">Short Term</span>
                                </span>
                                <span class="w-5 h-5 rounded-full border-2 border-outline-variant/40 bg-transparent flex items-center justify-center">
                                    <span class="student-duration-dot w-2 h-2 rounded-full bg-white opacity-0"></span>
                                </span>
                            </button>
                            <button type="button" id="student-duration-long" class="student-duration-btn flex-1 flex items-center justify-between p-3 rounded-xl border border-outline-variant/20 bg-surface-container-low hover:bg-surface-container transition-colors">
                                <span class="flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-secondary">
                                        <span class="material-symbols-outlined text-[18px]">flight_takeoff</span>
                                    </span>
                                    <span class="font-bold text-on-surface text-xs">Long Term</span>
                                </span>
                                <span class="w-5 h-5 rounded-full border-2 border-outline-variant/40 bg-transparent flex items-center justify-center">
                                    <span class="student-duration-dot w-2 h-2 rounded-full bg-white opacity-0"></span>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="student-program-name">Name of Program</label>
                        <input id="student-program-name" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g., Global Leadership Internship" type="text" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="student-start-year">Starting Year</label>
                            <div class="relative">
                                <input id="student-start-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" type="number" value="2024" min="1900" max="2100" />
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">calendar_month</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="student-end-year">End Year</label>
                            <div class="relative">
                                <input id="student-end-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" type="number" value="2025" min="1900" max="2100" />
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">event_available</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="px-5 py-3 shrink-0">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="student-mobility-entry-cancel" class="flex-1 bg-surface-container-high text-on-surface-variant py-2 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">Cancel</button>
                    <button type="submit" form="student-mobility-entry-form" id="student-mobility-entry-save" class="flex-1 bg-primary text-on-primary py-2 rounded-lg font-bold text-sm hover:shadow-xl hover:shadow-primary/20 active:scale-[0.98] transition-all">Log Mobility</button>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- International Scholarships & Fellowships modal (SRC - adapted, compact) -->
    <div id="scholarships-entry-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="scholarships-entry-modal-title">
        <div id="scholarships-entry-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="bg-gradient-to-r from-primary to-primary-container px-5 py-4 text-on-primary shrink-0">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase">Scholarship Entry</span>
                    <button type="button" id="scholarships-entry-modal-close" class="material-symbols-outlined text-lg text-on-primary hover:bg-white/10 rounded-full p-0.5 transition-colors shrink-0" aria-label="Close dialog">close</button>
                </div>
                <h2 id="scholarships-entry-modal-title" class="text-xl font-black leading-tight">International Scholarships and Fellowships</h2>
                <p class="text-on-primary-container text-xs mt-1.5 opacity-90 leading-snug">Registry of mobility initiatives and academic collaborations.</p>
                <div class="mt-4 flex bg-surface-container-low p-1 rounded-xl" role="group" aria-label="Classification">
                    <button type="button" id="scholarship-scope-inbound" class="scholarship-scope-btn flex-1 py-2 text-xs font-bold rounded-lg bg-primary text-on-primary shadow-sm transition-colors">Inbound</button>
                    <button type="button" id="scholarship-scope-outbound" class="scholarship-scope-btn flex-1 py-2 text-xs font-bold rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container transition-colors">Outbound</button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <form id="scholarships-entry-form" class="space-y-4">
                    <input type="hidden" id="scholarship-scope-value" name="scholarship_scope" value="inbound" />
                    <input type="hidden" id="scholarship-modality-value" name="scholarship_modality" value="on-site" />

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="scholarship-partner-institution">Partner Institution</label>
                        <input id="scholarship-partner-institution" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. University of Oxford" type="text" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="scholarship-country">Country</label>
                            <div class="relative">
                                <select id="scholarship-country" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option>Select Country</option>
                                    <option>United Kingdom</option>
                                    <option>Germany</option>
                                    <option>Singapore</option>
                                    <option>United States</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant/40">expand_more</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="scholarship-program-type">Type of Program/Activity</label>
                            <div class="relative">
                                <select id="scholarship-program-type" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option>Select Type</option>
                                    <option>Full Scholarship</option>
                                    <option>Short-term Fellowship</option>
                                    <option>Visiting Researcher</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant/40">expand_more</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="scholarship-field">Program/Field of Specialization</label>
                        <input id="scholarship-field" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. Advanced Bioengineering and Robotics" type="text" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="scholarship-faculty-count">Number of Faculty/Staff</label>
                            <input id="scholarship-faculty-count" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" type="number" value="0" min="0" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Modality</label>
                            <div class="flex gap-2">
                                <button type="button" id="scholarship-modality-onsite" class="scholarship-modality-btn flex-1 py-2 px-2 rounded-lg bg-surface-container-high text-primary font-bold text-[10px] flex items-center justify-center gap-1.5 border border-primary/20">
                                    <span class="material-symbols-outlined text-base">flight_takeoff</span>
                                    On-site
                                </button>
                                <button type="button" id="scholarship-modality-virtual" class="scholarship-modality-btn flex-1 py-2 px-2 rounded-lg bg-surface-container-low text-on-surface-variant font-bold text-[10px] flex items-center justify-center gap-1.5 border border-transparent hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-base">laptop_mac</span>
                                    Virtual
                                </button>
                                <button type="button" id="scholarship-modality-hybrid" class="scholarship-modality-btn flex-1 py-2 px-2 rounded-lg bg-surface-container-low text-on-surface-variant font-bold text-[10px] flex items-center justify-center gap-1.5 border border-transparent hover:bg-surface-container transition-colors">
                                    <span class="material-symbols-outlined text-base">layers</span>
                                    Hybrid
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="scholarship-start-year">Starting Year</label>
                            <input id="scholarship-start-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" placeholder="YYYY" type="number" min="1900" max="2100" />
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="scholarship-end-year">End Year</label>
                            <input id="scholarship-end-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" placeholder="YYYY" type="number" min="1900" max="2100" />
                        </div>
                    </div>
                </form>
            </div>

            <div class="px-5 py-3 shrink-0">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="scholarships-entry-discard" class="flex-1 bg-surface-container-high text-on-surface-variant py-2 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">Discard</button>
                    <button type="submit" form="scholarships-entry-form" id="scholarships-entry-save" class="flex-1 bg-primary text-on-primary py-2 rounded-lg font-bold text-sm hover:shadow-xl hover:shadow-primary/20 active:scale-[0.98] transition-all">Save Entry</button>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Staff Mobility & Scholarships modal (SRC - adapted, compact) -->
    <div id="staff-mobility-entry-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="staff-mobility-entry-modal-title">
        <div id="staff-mobility-entry-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="bg-gradient-to-r from-primary to-primary-container px-5 py-4 text-on-primary shrink-0">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase">New Entry</span>
                    <button type="button" id="staff-mobility-entry-modal-close" class="material-symbols-outlined text-lg text-on-primary hover:bg-white/10 rounded-full p-0.5 transition-colors shrink-0" aria-label="Close dialog">close</button>
                </div>
                <h2 id="staff-mobility-entry-modal-title" class="text-xl font-black leading-tight">Staff Mobility and Scholarships</h2>
                <p class="text-on-primary-container text-xs mt-1.5 opacity-90 leading-snug">Register a new international mobility record in the editorial system.</p>
                <div class="mt-4 flex bg-surface-container-low p-1 rounded-xl" role="group" aria-label="Classification">
                    <button type="button" id="staff-scope-inbound" class="staff-scope-btn flex-1 py-2 text-xs font-bold rounded-lg bg-primary text-on-primary shadow-sm transition-colors">Inbound</button>
                    <button type="button" id="staff-scope-outbound" class="staff-scope-btn flex-1 py-2 text-xs font-bold rounded-lg bg-surface-container-high text-on-surface-variant hover:bg-surface-container transition-colors">Outbound</button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <form id="staff-mobility-entry-form" class="space-y-4">
                    <input type="hidden" id="staff-scope-value" name="staff_scope" value="inbound" />
                    <input type="hidden" id="staff-modality-value" name="staff_modality" value="physical" />

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="staff-partner-institution">Partner Institution</label>
                        <div class="relative">
                            <input id="staff-partner-institution" class="w-full bg-surface-container-low border-none rounded-lg pl-9 pr-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. University of Oxford" type="text" />
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">account_balance</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="staff-country">Country</label>
                            <div class="relative">
                                <select id="staff-country" class="w-full appearance-none bg-surface-container-low border-none rounded-lg pl-9 pr-9 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option disabled selected value="">Select country</option>
                                    <option>United Kingdom</option>
                                    <option>Germany</option>
                                    <option>United States</option>
                                    <option>Canada</option>
                                    <option>Japan</option>
                                </select>
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">public</span>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">expand_more</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="staff-program-type">Type of Program Activity</label>
                            <div class="relative">
                                <select id="staff-program-type" class="w-full appearance-none bg-surface-container-low border-none rounded-lg pl-9 pr-9 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                    <option disabled selected value="">Select activity</option>
                                    <option>Teaching Mobility</option>
                                    <option>Research Scholarship</option>
                                    <option>Staff Training</option>
                                    <option>Administrative Exchange</option>
                                </select>
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">category</span>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">expand_more</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Modality</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button type="button" id="staff-modality-physical" class="staff-modality-btn flex items-center justify-center gap-1.5 py-2 border border-primary/20 bg-surface-container-high text-primary rounded-lg font-bold text-[10px] transition-colors">
                                <span class="material-symbols-outlined text-base">person</span>
                                Physical
                            </button>
                            <button type="button" id="staff-modality-virtual" class="staff-modality-btn flex items-center justify-center gap-1.5 py-2 border border-transparent bg-surface-container-low text-on-surface-variant hover:bg-surface-container rounded-lg font-bold text-[10px] transition-colors">
                                <span class="material-symbols-outlined text-base">laptop_mac</span>
                                Virtual
                            </button>
                            <button type="button" id="staff-modality-hybrid" class="staff-modality-btn flex items-center justify-center gap-1.5 py-2 border border-transparent bg-surface-container-low text-on-surface-variant hover:bg-surface-container rounded-lg font-bold text-[10px] transition-colors">
                                <span class="material-symbols-outlined text-base">diversity_3</span>
                                Hybrid
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="staff-start-year">Starting Year</label>
                            <div class="relative">
                                <input id="staff-start-year" class="w-full bg-surface-container-low border-none rounded-lg pl-9 pr-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" min="2020" max="2030" type="number" value="2024" />
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">calendar_today</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="staff-end-year">End Year</label>
                            <div class="relative">
                                <input id="staff-end-year" class="w-full bg-surface-container-low border-none rounded-lg pl-9 pr-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" min="2020" max="2030" type="number" value="2025" />
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">calendar_month</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="px-5 py-3 shrink-0">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="staff-mobility-entry-cancel" class="flex-1 bg-surface-container-high text-on-surface-variant py-2 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">Cancel</button>
                    <button type="submit" form="staff-mobility-entry-form" id="staff-mobility-entry-save" class="flex-1 bg-primary text-on-primary py-2 rounded-lg font-bold text-sm hover:shadow-xl hover:shadow-primary/20 active:scale-[0.98] transition-all">Save Mobility Record</button>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Full-Time Foreign Students modal (SRC - adapted, compact) -->
    <div id="full-time-foreign-students-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="full-time-foreign-students-title">
        <div id="full-time-foreign-students-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="bg-gradient-to-r from-primary to-primary-container px-5 py-4 text-on-primary shrink-0">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase">Student Entry</span>
                    <button type="button" id="full-time-foreign-students-close" class="material-symbols-outlined text-lg text-on-primary hover:bg-white/10 rounded-full p-0.5 transition-colors shrink-0" aria-label="Close dialog">close</button>
                </div>
                <h2 id="full-time-foreign-students-title" class="text-xl font-black leading-tight">Full-Time Foreign Students</h2>
                <p class="text-on-primary-container text-xs mt-1.5 opacity-90 leading-snug">Add a new student mobility record to the editorial database.</p>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <form id="full-time-foreign-students-form" class="space-y-4">
                    <input type="hidden" id="full-time-modality-value" name="full_time_modality" value="on-site" />
                    <input type="hidden" id="full-time-level-value" name="full_time_level" value="undergraduate" />

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-country">Country of Origin</label>
                            <div class="relative">
                                <input id="full-time-country" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Search country..." type="text" />
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">public</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-count">Number of Students</label>
                            <div class="relative">
                                <input id="full-time-count" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" min="1" type="number" value="1" />
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">groups</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-programs">Academic Programs Enrolled</label>
                        <div class="relative">
                            <input id="full-time-programs" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. International Business, Computer Science..." type="text" />
                            <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">school</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Modality</label>
                            <div class="flex bg-surface-container-low p-1 rounded-xl">
                                <button type="button" id="full-time-modality-onsite" class="full-time-modality-btn flex-1 py-2 px-2 text-[10px] font-bold rounded-lg bg-white shadow-sm text-primary">On-site</button>
                                <button type="button" id="full-time-modality-virtual" class="full-time-modality-btn flex-1 py-2 px-2 text-[10px] font-medium rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">Virtual</button>
                                <button type="button" id="full-time-modality-hybrid" class="full-time-modality-btn flex-1 py-2 px-2 text-[10px] font-medium rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">Hybrid</button>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Level of Study</label>
                            <div class="flex bg-surface-container-low p-1 rounded-xl">
                                <button type="button" id="full-time-level-undergraduate" class="full-time-level-btn flex-1 py-2 px-2 text-[10px] font-bold rounded-lg bg-white shadow-sm text-primary">Undergraduate</button>
                                <button type="button" id="full-time-level-graduate" class="full-time-level-btn flex-1 py-2 px-2 text-[10px] font-medium rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">Graduate</button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-start-year">Starting Year</label>
                            <div class="relative">
                                <select id="full-time-start-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 appearance-none transition-all">
                                    <option>2023</option>
                                    <option selected>2024</option>
                                    <option>2025</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">calendar_today</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-end-year">Estimated End Year</label>
                            <div class="relative">
                                <select id="full-time-end-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 appearance-none transition-all">
                                    <option>2026</option>
                                    <option>2027</option>
                                    <option selected>2028</option>
                                    <option>2029</option>
                                </select>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">event_upcoming</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="px-5 py-3 shrink-0">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="full-time-foreign-students-cancel" class="flex-1 bg-surface-container-high text-on-surface-variant py-2 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">Cancel</button>
                    <button type="submit" form="full-time-foreign-students-form" id="full-time-foreign-students-save" class="flex-1 bg-primary text-on-primary py-2 rounded-lg font-bold text-sm hover:shadow-xl hover:shadow-primary/20 active:scale-[0.98] transition-all">Save Student Record</button>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Full-Time Foreign Students detail modal -->
    <div id="full-time-students-detail-modal" class="hidden fixed inset-0 z-[101] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="full-time-students-detail-title">
        <div id="full-time-students-detail-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative w-full max-w-6xl max-h-[90vh] rounded-2xl overflow-hidden shadow-2xl border border-outline-variant/10 bg-surface flex flex-col">
            <div class="px-5 py-4 border-b border-outline-variant/10 flex items-start justify-between gap-4 shrink-0">
                <div>
                    <h3 id="full-time-students-detail-title" class="text-lg font-black tracking-tight text-on-surface">Full-Time Foreign Students</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Click Edit/Delete to manage records in this category.</p>
                </div>
                <button type="button" id="full-time-students-detail-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
            </div>
            <div class="px-5 py-4 flex-1 overflow-y-auto">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[980px]">
                        <thead>
                            <tr class="border-b border-outline-variant/10 text-on-surface">
                                <th class="py-2 px-2 text-xs font-bold">Country of Origin</th>
                                <th class="py-2 px-2 text-xs font-bold">Students</th>
                                <th class="py-2 px-2 text-xs font-bold">Programs</th>
                                <th class="py-2 px-2 text-xs font-bold">Modality</th>
                                <th class="py-2 px-2 text-xs font-bold">Starting Year</th>
                                <th class="py-2 px-2 text-xs font-bold">Estimated End Year</th>
                                <th class="py-2 px-2 text-xs font-bold">Action</th>
                            </tr>
                        </thead>
                        <tbody id="full-time-students-detail-body">
                            <tr>
                                <td colspan="7" class="py-4 px-2 text-sm text-on-surface-variant">Loading records...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-Time Foreign Faculty detail modal -->
    <div id="full-time-faculty-detail-modal" class="hidden fixed inset-0 z-[101] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="full-time-faculty-detail-title">
        <div id="full-time-faculty-detail-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative w-full max-w-6xl max-h-[90vh] rounded-2xl overflow-hidden shadow-2xl border border-outline-variant/10 bg-surface flex flex-col">
            <div class="px-5 py-4 border-b border-outline-variant/10 flex items-start justify-between gap-4 shrink-0">
                <div>
                    <h3 id="full-time-faculty-detail-title" class="text-lg font-black tracking-tight text-on-surface">Full-Time Foreign Faculty</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Click Edit/Delete to manage records in this category.</p>
                </div>
                <button type="button" id="full-time-faculty-detail-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
            </div>
            <div class="px-5 py-4 flex-1 overflow-y-auto">
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[980px]">
                        <thead>
                            <tr class="border-b border-outline-variant/10 text-on-surface">
                                <th class="py-2 px-2 text-xs font-bold">Scope</th>
                                <th class="py-2 px-2 text-xs font-bold">Full Name</th>
                                <th class="py-2 px-2 text-xs font-bold">Citizenship</th>
                                <th class="py-2 px-2 text-xs font-bold">Field of Specialization</th>
                                <th class="py-2 px-2 text-xs font-bold">Starting Year</th>
                                <th class="py-2 px-2 text-xs font-bold">End Year</th>
                                <th class="py-2 px-2 text-xs font-bold">Action</th>
                            </tr>
                        </thead>
                        <tbody id="full-time-faculty-detail-body">
                            <tr>
                                <td colspan="7" class="py-4 px-2 text-sm text-on-surface-variant">Loading records...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Full-Time Foreign Faculty modal (SRC - adapted, compact) -->
    <div id="full-time-foreign-faculty-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="full-time-foreign-faculty-title">
        <div id="full-time-foreign-faculty-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="bg-gradient-to-r from-primary to-primary-container px-5 py-4 text-on-primary shrink-0">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span class="bg-white/20 px-2 py-0.5 rounded-full text-[10px] font-bold tracking-widest uppercase">New Entry</span>
                    <button type="button" id="full-time-foreign-faculty-close" class="material-symbols-outlined text-lg text-on-primary hover:bg-white/10 rounded-full p-0.5 transition-colors shrink-0" aria-label="Close dialog">close</button>
                </div>
                <h2 id="full-time-foreign-faculty-title" class="text-xl font-black leading-tight">Full-Time Foreign Faculty</h2>
                <p class="text-on-primary-container text-xs mt-1.5 opacity-90 leading-snug">Enroll a new academic record into the mobility program.</p>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <form id="full-time-foreign-faculty-form" class="space-y-4">
                    <input type="hidden" id="full-time-faculty-scope-value" name="full_time_faculty_scope" value="inbound" />
                    <input type="hidden" id="full-time-faculty-level-value" name="full_time_faculty_level" value="undergraduate" />

                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Classification</label>
                        <div class="flex bg-surface-container-low p-1 rounded-xl">
                            <button type="button" id="full-time-faculty-scope-inbound" class="full-time-faculty-scope-btn flex-1 py-2 px-2 text-[10px] font-bold rounded-lg bg-white shadow-sm text-primary">Inbound</button>
                            <button type="button" id="full-time-faculty-scope-outbound" class="full-time-faculty-scope-btn flex-1 py-2 px-2 text-[10px] font-medium rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">Outbound</button>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-faculty-name">FULL NAME</label>
                        <div class="relative">
                            <input id="full-time-faculty-name" class="w-full bg-surface-container-low border-none rounded-lg pl-9 pr-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all font-medium placeholder:text-on-surface-variant/40" placeholder="Dr. Julianne Sterling" type="text" />
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">person</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-faculty-citizenship">CITIZENSHIP</label>
                            <div class="relative">
                                <select id="full-time-faculty-citizenship" class="w-full appearance-none bg-surface-container-low border-none rounded-lg pl-9 pr-9 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all font-medium">
                                    <option>United States</option>
                                    <option>United Kingdom</option>
                                    <option>Germany</option>
                                    <option>France</option>
                                    <option>Canada</option>
                                    <option>Australia</option>
                                    <option>Singapore</option>
                                </select>
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">public</span>
                                <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">expand_more</span>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="full-time-faculty-specialization">FIELD OF SPECIALIZATION</label>
                            <div class="relative">
                                <input id="full-time-faculty-specialization" class="w-full bg-surface-container-low border-none rounded-lg pl-9 pr-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all font-medium placeholder:text-on-surface-variant/40" placeholder="Quantum Mechanics" type="text" />
                                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none text-lg">school</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">CONTRACT DURATION</label>
                            <div class="flex items-center gap-2">
                                <input id="full-time-faculty-start-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all font-medium placeholder:text-on-surface-variant/40" placeholder="Start Year" type="number" />
                                <span class="text-on-surface-variant/40 font-bold">—</span>
                                <input id="full-time-faculty-end-year" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all font-medium placeholder:text-on-surface-variant/40" placeholder="End Year" type="number" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">TEACHING LEVEL</label>
                            <div class="flex bg-surface-container-low p-1 rounded-xl">
                                <button type="button" id="full-time-faculty-level-undergraduate" class="full-time-faculty-level-btn flex-1 py-2 px-2 text-[10px] font-bold rounded-lg bg-white shadow-sm text-primary">Undergraduate</button>
                                <button type="button" id="full-time-faculty-level-graduate" class="full-time-faculty-level-btn flex-1 py-2 px-2 text-[10px] font-medium rounded-lg text-on-surface-variant hover:bg-surface-container transition-colors">Graduate</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="px-5 py-3 shrink-0">
                <div class="flex flex-col sm:flex-row gap-2">
                    <button type="button" id="full-time-foreign-faculty-discard" class="flex-1 bg-surface-container-high text-tertiary py-2 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">Discard Draft</button>
                    <button type="button" id="full-time-foreign-faculty-cancel" class="flex-1 bg-surface-container-high text-on-surface-variant py-2 rounded-lg font-bold text-sm hover:bg-surface-container-highest transition-colors">Cancel</button>
                    <button type="submit" form="full-time-foreign-faculty-form" id="full-time-foreign-faculty-save" class="flex-1 bg-primary text-on-primary py-2 rounded-lg font-bold text-sm hover:shadow-xl hover:shadow-primary/20 active:scale-[0.98] transition-all">Save Faculty Record</button>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Internationalization of Research modal (SRC - adapted, compact) -->
    <div id="internationalization-research-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="internationalization-research-title">
        <div id="internationalization-research-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,740px)]">
            <div class="px-5 py-4 border-b border-outline-variant/10 shrink-0">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 id="internationalization-research-title" class="text-xl font-black tracking-tight text-on-surface">Internationalization of Research</h3>
                        <p class="text-xs text-on-surface-variant mt-1">Submit new research records for international collaboration and citations.</p>
                    </div>
                    <button type="button" id="internationalization-research-close" class="p-1.5 hover:bg-surface-container rounded-full transition-colors text-on-surface-variant">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <div class="mt-4 flex gap-1 p-1 bg-surface-container-low rounded-xl">
                    <button type="button" id="research-cat-collaborative" class="research-category-btn flex-1 py-2 px-2 rounded-lg text-[9px] font-bold bg-primary text-on-primary shadow-sm">COLLABORATIVE</button>
                    <button type="button" id="research-cat-sole" class="research-category-btn flex-1 py-2 px-2 rounded-lg text-[9px] font-bold text-on-surface-variant hover:bg-surface-container">SOLE AUTHOR</button>
                    <button type="button" id="research-cat-published" class="research-category-btn flex-1 py-2 px-2 rounded-lg text-[9px] font-bold text-on-surface-variant hover:bg-surface-container">PUBLISHED</button>
                    <button type="button" id="research-cat-citations" class="research-category-btn flex-1 py-2 px-2 rounded-lg text-[9px] font-bold text-on-surface-variant hover:bg-surface-container">CITATIONS</button>
                </div>
            </div>

            <form id="internationalization-research-form" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <input type="hidden" id="research-category-value" value="collaborative" />
                <input type="hidden" id="research-published-value" value="published" />

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-on-surface tracking-wider block">FACULTY NAME</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">person</span>
                        <input id="research-faculty-name" class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm transition-all outline-none" placeholder="Search or enter faculty name" type="text" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-on-surface tracking-wider block">FISCAL YEAR</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">calendar_today</span>
                        <select id="research-fiscal-year" class="w-full pl-9 pr-8 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm appearance-none outline-none">
                            <option>2024</option>
                            <option>2023</option>
                            <option>2022</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">expand_more</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-on-surface tracking-wider block">RESEARCH TITLE / TOPIC</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-3 text-primary-container">description</span>
                        <textarea id="research-title-topic" class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm outline-none resize-none" placeholder="Enter the full title of the research project..." rows="2"></textarea>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-on-surface tracking-wider block">PARTNER UNIVERSITY / AGENCY</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">school</span>
                            <input id="research-partner-agency" class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm outline-none" placeholder="Collaborating institution or journal" type="text" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-on-surface tracking-wider block">PARTNER COUNTRY</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">public</span>
                            <input id="research-partner-country" class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm outline-none" placeholder="Search country" type="text" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-on-surface tracking-wider block">PROJECT STATUS</label>
                        <div class="flex gap-4 mt-1">
                            <label class="flex items-center gap-2 cursor-pointer"><input checked class="w-4 h-4 text-primary focus:ring-primary/30 border-outline" name="research-status" type="radio" value="ongoing" /><span class="text-sm">Ongoing</span></label>
                            <label class="flex items-center gap-2 cursor-pointer"><input class="w-4 h-4 text-primary focus:ring-primary/30 border-outline" name="research-status" type="radio" value="completed" /><span class="text-sm">Completed</span></label>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-on-surface tracking-wider block">PUBLISHED STATUS</label>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs text-on-surface-variant">Not Published</span>
                            <button type="button" id="research-published-toggle" class="relative inline-flex h-6 w-11 rounded-full bg-primary-container transition-colors">
                                <span id="research-published-knob" class="translate-x-5 inline-block h-5 w-5 mt-0.5 transform rounded-full bg-white shadow transition"></span>
                            </button>
                            <span class="text-xs font-semibold text-primary-container">Published</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-on-surface tracking-wider block">SDG FOCUS</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">savings</span>
                        <input id="research-sdg-focus" class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm outline-none" placeholder="e.g. SDG 4, SDG 9" type="text" />
                    </div>
                </div>
            </form>

            <div class="px-5 py-3 border-t border-outline-variant/10 bg-surface-container-low flex justify-between items-center gap-2 shrink-0">
                <button type="button" id="research-cancel" class="text-on-surface-variant font-bold text-xs px-4 py-2 rounded-lg hover:bg-surface-container transition-all">CANCEL</button>
                <div class="flex gap-2">
                    <button type="button" id="research-save-draft" class="flex items-center gap-1 text-on-surface font-bold text-xs px-4 py-2 rounded-lg border border-outline-variant hover:bg-surface-container transition-all">
                        <span class="material-symbols-outlined text-base">drafts</span>
                        SAVE AS DRAFT
                    </button>
                    <button type="submit" form="internationalization-research-form" id="research-save-record" class="flex items-center gap-1 bg-primary text-on-primary font-bold text-xs px-4 py-2 rounded-lg shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-base">save</span>
                        SAVE RESEARCH RECORD
                    </button>
                </div>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- COIL Class modal (SRC - adapted, compact) -->
    <div id="coil-entry-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="coil-entry-modal-title">
        <div id="coil-entry-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="px-5 py-4 border-b border-outline-variant/10 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="coil-entry-modal-title" class="text-xl font-black tracking-tight text-on-surface">New COIL Class</h3>
                        <p class="text-xs text-on-surface-variant mt-1">Add a new Collaborative Online International Learning record to the editorial database.</p>
                    </div>
                    <button type="button" id="coil-entry-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
                </div>
            </div>

            <form id="coil-entry-form" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="coil-partner-university">Partner University</label>
                    <div class="relative">
                        <input id="coil-partner-university" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Search for participating institution..." type="text" />
                        <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">school</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="coil-country">Country</label>
                        <div class="relative">
                            <select id="coil-country" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                <option disabled selected value="">Select destination</option>
                                <option>United Kingdom</option>
                                <option>Japan</option>
                                <option>Germany</option>
                                <option>Canada</option>
                                <option>Australia</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">public</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="coil-year">Year</label>
                        <div class="relative">
                            <select id="coil-year" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                <option>Select year</option>
                                <option>2024 / 2025</option>
                                <option>2023 / 2024</option>
                                <option>2025 / 2026</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">calendar_today</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="coil-subject">COIL Subject</label>
                    <div class="relative">
                        <input id="coil-subject" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. Sustainable Urban Planning & Mobility" type="text" />
                        <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">subject</span>
                    </div>
                </div>

                <div class="flex gap-3 p-4 rounded-lg bg-surface-container">
                    <span class="material-symbols-outlined text-primary-container" style="font-variation-settings:'FILL' 1;">info</span>
                    <p class="text-xs text-on-surface-variant leading-relaxed">
                        Entry of a new COIL Class will automatically notify the regional mobility coordinator and update the active Editorial Data dashboard.
                    </p>
                </div>
            </form>

            <div class="px-5 py-3 shrink-0 flex items-center justify-end gap-2 sm:gap-3 border-t border-outline-variant/10">
                <button type="button" id="coil-entry-cancel" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors rounded-lg">
                    Cancel
                </button>
                <button type="submit" form="coil-entry-form" id="coil-entry-save" class="flex-1 sm:flex-none px-5 py-2 text-xs font-bold text-on-primary bg-primary rounded-lg shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                    Save COIL Class
                </button>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Transnational Education Program modal (SRC - adapted, compact) -->
    <div id="transnational-education-program-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="transnational-education-program-title">
        <div id="transnational-education-program-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="px-5 py-4 border-b border-outline-variant/10 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="transnational-education-program-title" class="text-xl font-black tracking-tight text-on-surface">New Transnational Education Program</h3>
                        <p class="text-xs text-on-surface-variant mt-1">Add a new transnational academic record to the editorial database.</p>
                    </div>
                    <button type="button" id="transnational-education-program-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
                </div>
            </div>

            <form id="transnational-education-program-form" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="tn-partner-university">Partner University</label>
                    <div class="relative">
                        <input id="tn-partner-university" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Enter institution name" type="text" />
                        <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">account_balance</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="tn-country">Country</label>
                        <div class="relative">
                            <select id="tn-country" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                <option disabled selected value="">Select country</option>
                                <option>United Kingdom</option>
                                <option>Australia</option>
                                <option>United States</option>
                                <option>Canada</option>
                                <option>Singapore</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">public</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="tn-year-started">Year Started</label>
                        <div class="relative">
                            <input id="tn-year-started" class="w-full bg-surface-container-low border-none rounded-lg pl-3 pr-9 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" min="1900" max="2099" placeholder="2024" type="number" />
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">calendar_today</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="tn-academic-program">Academic Program</label>
                    <input id="tn-academic-program" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Degree name (e.g., MSc in International Business)" type="text" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">CHED Permit/Approval</label>
                    <label for="tn-ched-permit" class="border-2 border-dashed border-outline-variant/30 rounded-lg p-6 flex flex-col items-center justify-center bg-surface-container-low/50 hover:bg-surface-container-low transition-colors cursor-pointer group">
                        <span class="material-symbols-outlined text-primary text-3xl mb-2 opacity-60 group-hover:opacity-100 transition-opacity">cloud_upload</span>
                        <span class="text-sm font-semibold text-on-surface">Click to upload permit document</span>
                        <span class="text-xs text-on-surface-variant/60 mt-1">PDF, JPG, or PNG (Max 10MB)</span>
                    </label>
                    <input id="tn-ched-permit" class="hidden" type="file" accept="application/pdf,image/jpeg,image/png" />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="tn-students">Number of Students</label>
                        <input id="tn-students" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all" placeholder="0" type="number" />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="tn-program-status">Program Status</label>
                        <div class="relative">
                            <select id="tn-program-status" class="w-full appearance-none bg-surface-container-low border-none rounded-lg pl-3 pr-9 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                <option disabled selected value="">Select status</option>
                                <option>Ongoing</option>
                                <option>Completed</option>
                                <option>Planned</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">flag</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Nationalities</label>
                    <div id="tn-nationalities" class="flex flex-wrap gap-2 p-2 bg-surface-container-low rounded-lg min-h-[44px] items-center">
                        <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 tn-nationality-chip">
                            FILIPINO
                            <button type="button" class="tn-chip-remove p-0.5 rounded hover:bg-primary/10" aria-label="Remove nationality">
                                <span class="material-symbols-outlined text-[12px] text-primary">close</span>
                            </button>
                        </span>
                        <span class="bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 tn-nationality-chip">
                            BRITISH
                            <button type="button" class="tn-chip-remove p-0.5 rounded hover:bg-primary/10" aria-label="Remove nationality">
                                <span class="material-symbols-outlined text-[12px] text-primary">close</span>
                            </button>
                        </span>
                        <input id="tn-nationality-input" class="bg-transparent border-none p-0 h-full text-sm w-20 focus:ring-0" placeholder="Add..." type="text" />
                    </div>
                    <p class="text-[11px] text-on-surface-variant/70 ml-1">Press Enter to add a nationality.</p>
                </div>
            </form>

            <div class="px-5 py-3 shrink-0 flex items-center justify-end gap-2 sm:gap-3 border-t border-outline-variant/10">
                <button type="button" id="tn-program-cancel" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors rounded-lg">
                    Cancel
                </button>
                <button type="submit" form="transnational-education-program-form" id="tn-program-save" class="flex-1 sm:flex-none px-5 py-2 text-xs font-bold text-on-primary bg-primary rounded-lg shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                    Save Program Record
                    <span class="material-symbols-outlined text-sm">save</span>
                </button>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- Collaborative Events & Activities modal (SRC - adapted, compact) -->
    <div id="collaborative-events-activities-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="collaborative-events-activities-modal-title">
        <div id="collaborative-events-activities-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="px-5 py-4 border-b border-outline-variant/10 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="collaborative-events-activities-modal-title" class="text-xl font-black tracking-tight text-on-surface">Collaborative Event/Activity</h3>
                        <p class="text-xs text-on-surface-variant mt-1">Add a collaborative event/activity record (name, partners, countries, year, participants).</p>
                    </div>
                    <button type="button" id="collaborative-events-activities-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
                </div>
            </div>

            <form id="collaborative-events-activities-form" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="cea-event-name">Collaborative Event/ Activity</label>
                    <input id="cea-event-name" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. International Research Symposium" type="text" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="cea-partner-input">Partner(s)</label>
                    <div class="relative">
                        <input id="cea-partner-input" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 pr-11 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Add educational or institutional partners" type="text" />
                        <button type="button" id="cea-partner-add" class="absolute right-2 top-1/2 -translate-y-1/2 material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Add partner">
                            add_circle
                        </button>
                    </div>
                    <div id="cea-partners" class="flex flex-wrap gap-2 mt-2 min-h-[44px] items-center"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="cea-country">Country(ies)</label>
                        <select id="cea-country" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                            <option disabled selected value="">Select Countries</option>
                            <option>United Kingdom</option>
                            <option>Germany</option>
                            <option>Canada</option>
                            <option>Australia</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="cea-year">Year</label>
                        <select id="cea-year" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                            <option disabled selected value="">Select year</option>
                            <option>2024</option>
                            <option>2023</option>
                            <option>2022</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="cea-participants">Number of Participants</label>
                    <div class="flex items-center gap-3 bg-surface-container-low rounded-lg p-2">
                        <input id="cea-participants" class="flex-1 bg-transparent border-none rounded-lg px-2 py-2 text-on-surface focus:ring-0 outline-none" type="number" value="0" min="0" />
                        <div class="flex gap-2 pr-1">
                            <button type="button" id="cea-participants-minus" class="w-9 h-9 flex items-center justify-center rounded bg-surface-container hover:bg-surface-container-high transition-colors text-primary font-bold" aria-label="Decrease participants">－</button>
                            <button type="button" id="cea-participants-plus" class="w-9 h-9 flex items-center justify-center rounded bg-surface-container hover:bg-surface-container-high transition-colors text-primary font-bold" aria-label="Increase participants">＋</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="px-5 py-3 shrink-0 flex items-center justify-end gap-2 sm:gap-3 border-t border-outline-variant/10">
                <button type="button" id="cea-cancel" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors rounded-lg">
                    Cancel
                </button>
                <button type="submit" form="collaborative-events-activities-form" id="cea-save" class="flex-1 sm:flex-none px-5 py-2 text-xs font-bold text-on-primary bg-primary rounded-lg shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                    Save Activity
                </button>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- In-house ASEAN and Internationalization Events modal (SRC - adapted, compact) -->
    <div id="in-house-asean-internationalization-events-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="in-house-asean-internationalization-events-modal-title">
        <div id="in-house-asean-internationalization-events-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="px-5 py-4 border-b border-outline-variant/10 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="in-house-asean-internationalization-events-modal-title" class="text-xl font-black tracking-tight text-on-surface">New In-house Event</h3>
                        <p class="text-xs text-on-surface-variant mt-1">Record new institutional internationalization activities.</p>
                    </div>
                    <button type="button" id="in-house-asean-internationalization-events-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
                </div>
            </div>

            <form id="in-house-asean-internationalization-events-form" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="inhouse-event-name">ASEAN and Internationalization Event</label>
                    <input id="inhouse-event-name" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g., Southeast Asian Cultural Exchange Forum" type="text" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="inhouse-partner-input">Partner(s)</label>
                    <div class="relative">
                        <input id="inhouse-partner-input" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 pr-11 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Add educational or institutional partners" type="text" />
                        <button type="button" id="inhouse-partner-add" class="absolute right-2 top-1/2 -translate-y-1/2 material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Add partner">add_circle</button>
                    </div>
                    <div id="inhouse-partners" class="flex flex-wrap gap-2 mt-2 min-h-[44px] items-center"></div>
                    <p class="text-[11px] text-on-surface-variant/70 ml-1 mt-1">Press Enter to add a partner.</p>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Country(ies)</label>
                    <div class="flex gap-2">
                        <select id="inhouse-country-select" class="flex-1 appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                            <option disabled selected value="">Select</option>
                            <option>Singapore</option>
                            <option>Thailand</option>
                            <option>Malaysia</option>
                            <option>Indonesia</option>
                            <option>Vietnam</option>
                        </select>
                        <button type="button" id="inhouse-country-add" class="px-3 py-2 rounded-lg bg-secondary-container text-on-surface text-sm font-bold hover:opacity-90 active:opacity-80 transition-all">
                            Select
                        </button>
                    </div>
                    <div id="inhouse-countries" class="flex flex-wrap gap-2 mt-2 min-h-[44px] items-center p-2 bg-surface-container-low rounded-lg"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="inhouse-participants">Number of Participants</label>
                        <div class="flex items-center gap-2 bg-surface-container-low rounded-lg p-2">
                            <input id="inhouse-participants" class="flex-1 bg-transparent border-none rounded-lg px-2 py-2 text-on-surface focus:ring-0 outline-none" type="number" value="0" min="0" />
                            <div class="flex gap-2">
                                <button type="button" id="inhouse-participants-minus" class="w-9 h-9 flex items-center justify-center rounded bg-surface-container hover:bg-surface-container-high transition-colors text-primary font-bold" aria-label="Decrease participants">－</button>
                                <button type="button" id="inhouse-participants-plus" class="w-9 h-9 flex items-center justify-center rounded bg-surface-container hover:bg-surface-container-high transition-colors text-primary font-bold" aria-label="Increase participants">＋</button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="inhouse-year">Year</label>
                        <div class="relative">
                            <select id="inhouse-year" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                <option disabled selected value="">Select year</option>
                                <option>2024</option>
                                <option>2023</option>
                                <option>2022</option>
                                <option>2021</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl overflow-hidden h-24 relative group bg-surface-container">
                    <img class="w-full h-full object-cover opacity-40 group-hover:scale-105 transition-transform duration-700" alt="ASEAN regional impact visualization" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCM_Ey5CxaoCn7AwOC5joLrysW6gnWexxadmgQzA-3lrK1AgVgSlKGUBFqDm9qwDOb7QHDv9Z-i3pZLBSD1CPzgAFI3yaEhnFGaUp6fbUFldMg2ueEY-TdVqX-6OmAjTXI87AqBt3m7iWOwUdT3ZrHfozrU3qnSUuQQzuz-poyHVTOkzDeaSdKKcOZpQFJRqv7a-9coj8LTL0gpus4vIq4JG3KWYRFbGhae7bf4_h_LrBmdpHA0mmybJLOVq2w8S5uzGk5rUlqOJEA" />
                    <div class="absolute inset-0 bg-gradient-to-t from-surface-container-lowest to-transparent flex items-end p-3">
                        <span class="text-[10px] font-bold tracking-widest uppercase text-on-surface-variant opacity-60">Regional Impact Visualization</span>
                    </div>
                </div>
            </form>

            <div class="px-5 py-3 shrink-0 flex items-center justify-end gap-2 sm:gap-3 border-t border-outline-variant/10">
                <button type="button" id="inhouse-cancel" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors rounded-lg">
                    Cancel
                </button>
                <button type="submit" form="in-house-asean-internationalization-events-form" id="inhouse-save" class="flex-1 sm:flex-none px-5 py-2 text-xs font-bold text-on-primary bg-primary rounded-lg shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                    Save Event
                </button>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- International/Sustainability Centers modal (SRC - adapted, compact) -->
    <div id="international-sustainability-centers-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="international-sustainability-centers-modal-title">
        <div id="international-sustainability-centers-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="px-5 py-4 border-b border-outline-variant/10 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="international-sustainability-centers-modal-title" class="text-xl font-black tracking-tight text-on-surface">Register Sustainability Center</h3>
                        <p class="text-xs text-on-surface-variant mt-1">International centers categorized under global sustainability networks.</p>
                    </div>
                    <button type="button" id="international-sustainability-centers-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
                </div>
            </div>

            <form id="international-sustainability-centers-form" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="isc-center-name">International Center</label>
                    <input id="isc-center-name" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. SEAMEO Regional Centre for Higher Education" type="text" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Partner(s)</label>
                    <div class="flex flex-wrap gap-2 p-3 bg-surface-container-low rounded-xl min-h-[48px] items-center">
                        <div id="isc-partners" class="flex flex-wrap gap-2 items-center"></div>
                        <input id="isc-partner-input" class="flex-1 min-w-[120px] bg-transparent border-none focus:ring-0 text-sm p-0 placeholder:text-on-surface-variant/40" placeholder="Add partner..." type="text" />
                    </div>
                    <p class="text-[11px] text-on-surface-variant/70 ml-1">Press Enter to add a partner.</p>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">Country(ies)</label>
                    <div class="relative">
                        <input id="isc-country-input" class="w-full bg-surface-container-low border-none rounded-xl px-3 py-2 pr-10 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all font-medium" placeholder="Select countries..." type="text" />
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant/60 pointer-events-none">search</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="isc-year-established">Year Established</label>
                        <select id="isc-year-established" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                            <option disabled selected value="">Select year</option>
                            <option>2024</option>
                            <option>2023</option>
                            <option>2022</option>
                            <option>2021</option>
                            <option>2020</option>
                            <option>Earlier</option>
                        </select>
                    </div>
                </div>

                <div class="bg-surface-container-lowest flex gap-4 p-4 rounded-lg border border-outline-variant/10">
                    <span class="material-symbols-outlined text-primary">info</span>
                    <div>
                        <p class="text-[11px] font-bold text-primary uppercase tracking-tight mb-1">Categorization Guidance</p>
                        <p class="text-[12px] text-on-surface-variant leading-relaxed">
                            Ensure the center is formally recognized by the host HEI and the international governing body. Documentation proof will be required in the next step.
                        </p>
                    </div>
                </div>
            </form>

            <div class="px-5 py-3 shrink-0 flex items-center justify-end gap-2 sm:gap-3 border-t border-outline-variant/10">
                <button type="button" id="isc-cancel" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors rounded-lg">
                    Cancel
                </button>
                <button type="submit" form="international-sustainability-centers-form" id="isc-save" class="flex-1 sm:flex-none px-5 py-2 text-xs font-bold text-on-primary bg-primary rounded-lg shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                    Save Center
                </button>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <!-- StudyPH Program modal (SRC - adapted, compact) -->
    <div id="studyph-program-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="studyph-program-modal-title">
        <div id="studyph-program-modal-backdrop" class="absolute inset-0 bg-on-surface/40 backdrop-blur-md" aria-hidden="true"></div>
        <div class="relative z-10 w-full max-w-md bg-surface-container-lowest rounded-xl shadow-2xl overflow-hidden flex flex-col max-h-[min(92vh,720px)]">
            <div class="px-5 py-4 border-b border-outline-variant/10 shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 id="studyph-program-modal-title" class="text-xl font-black tracking-tight text-on-surface">Add StudyPH Program Entry</h3>
                        <p class="text-xs text-on-surface-variant mt-1">Populate the editorial database with a new StudyPH program record.</p>
                    </div>
                    <button type="button" id="studyph-program-modal-close" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container rounded-full p-1 transition-colors" aria-label="Close dialog">close</button>
                </div>
            </div>

            <form id="studyph-program-form" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-year">Year</label>
                        <div class="relative">
                            <select id="studyph-year" class="w-full appearance-none bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface focus:ring-2 focus:ring-primary/30 transition-all cursor-pointer">
                                <option disabled selected value="">Select year</option>
                                <option>2024</option>
                                <option>2023</option>
                                <option>2022</option>
                                <option>2021</option>
                            </select>
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-on-surface-variant/40 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-kra">KRA</label>
                        <div class="relative">
                            <input id="studyph-kra" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. Academic Excellence" type="text" />
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-project-title">Project Title</label>
                    <input id="studyph-project-title" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Full name of the mobility project" type="text" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-field-area">Field/Area</label>
                    <input id="studyph-field-area" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. Engineering, Social Sciences" type="text" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1">UN SDG Covered</label>
                    <div class="flex flex-wrap gap-2" id="studyph-sdg-pills">
                        <button type="button" class="studyph-sdg-pill px-3 py-1.5 rounded-full border border-primary/20 text-[10px] font-bold text-primary" data-value="SDG 4: Quality Education">SDG 4: Quality Education</button>
                        <button type="button" class="studyph-sdg-pill px-3 py-1.5 rounded-full border border-outline-variant/30 text-[10px] font-bold text-on-surface-variant" data-value="SDG 10: Reduced Inequalities">SDG 10: Reduced Inequalities</button>
                        <button type="button" class="studyph-sdg-pill px-3 py-1.5 rounded-full border border-outline-variant/30 text-[10px] font-bold text-on-surface-variant" data-value="SDG 17: Partnerships">SDG 17: Partnerships</button>
                    </div>
                    <input type="hidden" id="studyph-sdgs-value" name="studyph_sdgs" value="" />
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-description">Description</label>
                    <textarea id="studyph-description" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" rows="3" placeholder="Provide a brief overview of the program objectives and impact..."></textarea>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-amount">Amount (PHP)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-on-surface-variant text-sm">₱</span>
                        <input id="studyph-amount" class="w-full bg-surface-container-low border-none rounded-lg pl-8 pr-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="0.00" type="number" min="0" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-beneficiaries-qty">Beneficiaries (Qty)</label>
                        <input id="studyph-beneficiaries-qty" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="Qty" type="number" min="0" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-beneficiaries-type">Beneficiaries Type</label>
                        <input id="studyph-beneficiaries-type" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="student, faculty, community, IP..." type="text" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-kpi">KPI</label>
                        <input id="studyph-kpi" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. Completion rate percentage" type="text" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-on-surface-variant ml-1" for="studyph-kpi-value">Value (KPI Attainment)</label>
                        <input id="studyph-kpi-value" class="w-full bg-surface-container-low border-none rounded-lg px-3 py-2 text-on-surface placeholder:text-on-surface-variant/40 focus:ring-2 focus:ring-primary/30 transition-all" placeholder="e.g. 85%" type="text" />
                    </div>
                </div>
            </form>

            <div class="px-5 py-3 shrink-0 flex items-center justify-end gap-2 sm:gap-3 border-t border-outline-variant/10">
                <button type="button" id="studyph-cancel" class="flex-1 sm:flex-none px-4 py-2 text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors rounded-lg">
                    Cancel
                </button>
                <button type="submit" form="studyph-program-form" id="studyph-save" class="flex-1 sm:flex-none px-5 py-2 text-xs font-bold text-on-primary bg-primary rounded-lg shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                    Save Entry
                </button>
            </div>
            <div class="h-1 bg-gradient-to-r from-primary via-secondary to-tertiary"></div>
        </div>
    </div>

    <script>
    (function () {
        var activeTabClasses = ['bg-primary', 'text-on-primary', 'shadow-md'];
        var inactiveTabClasses = ['bg-surface-container-high', 'text-on-surface-variant', 'hover:bg-surface-container'];
        var tabsPanelsSection = document.getElementById('mobility-tabs-panels-section');

        function revealTabsPanelsSection() {
            if (tabsPanelsSection) tabsPanelsSection.style.display = '';
        }

        function normalizeCounterValue(value) {
            var n = Number(value);
            if (!isFinite(n) || n < 0) return 0;
            return Math.floor(n);
        }

        function setMobilityCounter(id, value, isPercent) {
            var el = document.getElementById(id);
            if (!el) return;
            var safeValue = normalizeCounterValue(value);
            el.textContent = isPercent ? (safeValue + '%') : String(safeValue);
        }

        function normalizePercentValue(value) {
            var n = Number(value);
            if (!isFinite(n) || n < 0) return 0;
            if (n > 100) return 100;
            return Math.round(n);
        }

        function setTrendAnalysisMetric(labelId, barId, value) {
            var pct = normalizePercentValue(value);
            var labelEl = document.getElementById(labelId);
            if (labelEl) labelEl.textContent = '+' + pct + '%';
            var barEl = document.getElementById(barId);
            if (barEl) barEl.style.width = pct + '%';
        }

        async function loadMobilityCounters() {
            try {
                var resp = await fetch('api/mobility-counters.php?ts=' + Date.now(), {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) {
                    throw new Error((result && result.error) ? result.error : 'Failed to load counters');
                }

                var counters = result.data || {};
                setMobilityCounter('kpi-international-awards', counters.international_awards, false);
                setMobilityCounter('kpi-active-partnerships', counters.active_partnerships, false);
                setMobilityCounter('kpi-outgoing-exchange-students', counters.outgoing_exchange_students, false);
                setMobilityCounter('kpi-incoming-exchange-students', counters.incoming_exchange_students, false);
                setMobilityCounter('kpi-international-faculty-experts', counters.international_faculty_experts, false);
                setMobilityCounter('kpi-internationalization-target-met', counters.internationalization_target_met_percent, true);
                setMobilityCounter('kpi-joint-degree-programs', counters.joint_degree_programs, false);
                setMobilityCounter('kpi-transnational-centers', counters.transnational_centers, false);
                setMobilityCounter('kpi-international-internships', counters.international_internships, false);
                setMobilityCounter('kpi-research-grants', counters.research_grants, false);
                setMobilityCounter('kpi-scholarship-slots', counters.scholarship_slots, false);
                setMobilityCounter('kpi-asean-event-attendees', counters.asean_event_attendees, false);
                setTrendAnalysisMetric('trend-inbound-students-label', 'trend-inbound-students-bar', counters.trend_inbound_students_percent);
                setTrendAnalysisMetric('trend-faculty-mobility-label', 'trend-faculty-mobility-bar', counters.trend_faculty_mobility_percent);
                setTrendAnalysisMetric('trend-global-awards-label', 'trend-global-awards-bar', counters.trend_global_awards_percent);
            } catch (_) {
                // Keep the visual defaults at 0/0% if API is unavailable.
            }
        }

        var awardsTbody = document.getElementById('awards-table-body');
        var awardsAddModal = document.getElementById('awards-add-modal');
        var awardsAddActiveTab = 'institutional';
        var currentAwardsDetailIndicator = '';
        var editingAwardsDetailId = null;
        var awardsDetailModal = document.getElementById('awards-detail-modal');
        var awardsDetailTitle = document.getElementById('awards-detail-title');
        var awardsDetailFilesBody = document.getElementById('awards-detail-files-body');

        function showAwardsNotice(message, isError) {
            var el = document.createElement('div');
            el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                (isError ? 'bg-error text-on-error' : 'bg-surface-container-highest text-on-surface');
            el.textContent = message;
            document.body.appendChild(el);
            setTimeout(function () {
                if (el && el.parentNode) el.parentNode.removeChild(el);
            }, 2500);
        }

        function resetAwardsAddModalForm() {
            editingAwardsDetailId = null;
            [
                'awards-add-inst-name',
                'awards-add-inst-body',
                'awards-add-inst-year',
                'awards-add-accred-name',
                'awards-add-accred-body',
                'awards-add-accred-year',
                'awards-add-ranking-name',
                'awards-add-ranking-rank',
                'awards-add-ranking-year'
            ].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = '';
            });
            setAwardsAddTab('institutional');
            var titleEl = document.getElementById('awards-add-title');
            if (titleEl) titleEl.textContent = 'Add International Award Record';
            var saveBtn = document.getElementById('awards-add-modal-save');
            if (saveBtn) saveBtn.textContent = 'Save Record';
        }

        function setAwardsAddTab(tabName) {
            awardsAddActiveTab = tabName;
            document.querySelectorAll('.awards-add-tab-btn').forEach(function (btn) {
                var on = btn.getAttribute('data-awards-add-tab') === tabName;
                btn.classList.toggle('bg-primary', on);
                btn.classList.toggle('text-on-primary', on);
                btn.classList.toggle('bg-surface-container', !on);
                btn.classList.toggle('text-on-surface-variant', !on);
            });
            var map = {
                institutional: 'awards-add-panel-institutional',
                accreditation: 'awards-add-panel-accreditation',
                ranking: 'awards-add-panel-ranking'
            };
            Object.keys(map).forEach(function (key) {
                var panel = document.getElementById(map[key]);
                if (!panel) return;
                panel.classList.toggle('hidden', key !== tabName);
            });
        }

        function openAwardsAddModal(detailItem) {
            if (!awardsAddModal) return;
            resetAwardsAddModalForm();

            if (detailItem && typeof detailItem === 'object') {
                editingAwardsDetailId = Number(detailItem.id) || null;
                var indicator = String(detailItem.indicator_name || '').trim();
                if (indicator === 'International Accreditations, Assessments and Rating') {
                    setAwardsAddTab('accreditation');
                    var accName = document.getElementById('awards-add-accred-name');
                    var accBody = document.getElementById('awards-add-accred-body');
                    var accYear = document.getElementById('awards-add-accred-year');
                    if (accName) accName.value = String(detailItem.entry_title || '');
                    if (accBody) accBody.value = String(detailItem.entry_source || '');
                    if (accYear) accYear.value = detailItem.entry_year == null ? '' : String(detailItem.entry_year);
                } else if (indicator === 'International Rankings and Ratings') {
                    setAwardsAddTab('ranking');
                    var rankingName = document.getElementById('awards-add-ranking-name');
                    var rankingRank = document.getElementById('awards-add-ranking-rank');
                    var rankingYear = document.getElementById('awards-add-ranking-year');
                    if (rankingName) rankingName.value = String(detailItem.entry_title || '');
                    if (rankingRank) rankingRank.value = String(detailItem.entry_rank || '');
                    if (rankingYear) rankingYear.value = detailItem.entry_year == null ? '' : String(detailItem.entry_year);
                } else {
                    setAwardsAddTab('institutional');
                    var instName = document.getElementById('awards-add-inst-name');
                    var instBody = document.getElementById('awards-add-inst-body');
                    var instYear = document.getElementById('awards-add-inst-year');
                    if (instName) instName.value = String(detailItem.entry_title || '');
                    if (instBody) instBody.value = String(detailItem.entry_source || '');
                    if (instYear) instYear.value = detailItem.entry_year == null ? '' : String(detailItem.entry_year);
                }

                var editTitleEl = document.getElementById('awards-add-title');
                if (editTitleEl) editTitleEl.textContent = 'Edit International Award Record';
                var editSaveBtn = document.getElementById('awards-add-modal-save');
                if (editSaveBtn) editSaveBtn.textContent = 'Update Record';
            }

            awardsAddModal.classList.remove('hidden');
            awardsAddModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var firstInput = document.querySelector('#awards-add-panel-' + awardsAddActiveTab + ' input');
            if (firstInput) firstInput.focus();
        }

        function closeAwardsAddModal() {
            if (!awardsAddModal) return;
            awardsAddModal.classList.add('hidden');
            awardsAddModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function normalizeAwardDetailValue(value) {
            var text = String(value == null ? '' : value).trim();
            if (!text) return '';
            if (/^-?\d+\.0+$/.test(text)) {
                return text.replace(/\.0+$/, '');
            }
            return text;
        }

        function resolveAwardDotClass(colorToken) {
            var token = String(colorToken || '').toLowerCase();
            var map = {
                violet: 'bg-violet-500',
                blue: 'bg-blue-500',
                orange: 'bg-orange-500',
                emerald: 'bg-emerald-500',
                pink: 'bg-pink-500'
            };
            return map[token] || 'bg-primary';
        }

        function closeAwardsDetailModal() {
            if (!awardsDetailModal) return;
            currentAwardsDetailIndicator = '';
            awardsDetailModal.classList.add('hidden');
            awardsDetailModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        async function saveAwardsAddRecord() {
            var indicator = '';
            var title = '';
            var source = '';
            var rank = '';
            var yearText = '';

            if (awardsAddActiveTab === 'institutional') {
                indicator = 'International Institutional Awards';
                var instName = document.getElementById('awards-add-inst-name');
                var instBody = document.getElementById('awards-add-inst-body');
                var instYear = document.getElementById('awards-add-inst-year');
                title = (instName && instName.value ? instName.value : '').trim();
                source = (instBody && instBody.value ? instBody.value : '').trim();
                yearText = (instYear && instYear.value ? instYear.value : '').trim();
            } else if (awardsAddActiveTab === 'accreditation') {
                indicator = 'International Accreditations, Assessments and Rating';
                var accName = document.getElementById('awards-add-accred-name');
                var accBody = document.getElementById('awards-add-accred-body');
                var accYear = document.getElementById('awards-add-accred-year');
                title = (accName && accName.value ? accName.value : '').trim();
                source = (accBody && accBody.value ? accBody.value : '').trim();
                yearText = (accYear && accYear.value ? accYear.value : '').trim();
            } else {
                indicator = 'International Rankings and Ratings';
                var rankName = document.getElementById('awards-add-ranking-name');
                var rankValue = document.getElementById('awards-add-ranking-rank');
                var rankYear = document.getElementById('awards-add-ranking-year');
                title = (rankName && rankName.value ? rankName.value : '').trim();
                rank = (rankValue && rankValue.value ? rankValue.value : '').trim();
                yearText = (rankYear && rankYear.value ? rankYear.value : '').trim();
            }

            if (!indicator || !title) {
                showAwardsNotice('Indicator and title are required.', true);
                return;
            }
            if (yearText !== '' && !/^\d{4}$/.test(yearText)) {
                showAwardsNotice('Year must be 4 digits (or left blank).', true);
                return;
            }

            var form = new FormData();
            form.append('indicator_name', indicator);
            form.append('entry_title', title);
            form.append('entry_source', source);
            form.append('entry_rank', rank);
            form.append('entry_year', yearText);
            if (editingAwardsDetailId) form.append('detail_id', String(editingAwardsDetailId));

            try {
                var action = editingAwardsDetailId ? 'update_detail' : 'add_detail';
                var resp = await fetch('api/mobility-international-awards.php?action=' + action, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin'
                });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) {
                    throw new Error((result && result.error) ? result.error : 'Failed to save record');
                }
                closeAwardsAddModal();
                await loadAwardsRecords();
                if (awardsDetailModal && !awardsDetailModal.classList.contains('hidden') && currentAwardsDetailIndicator) {
                    await loadAwardsDetailRecords(currentAwardsDetailIndicator);
                }
                showAwardsNotice(editingAwardsDetailId ? 'Award record updated successfully.' : 'Award record added successfully.', false);
            } catch (err) {
                showAwardsNotice((err && err.message) ? err.message : 'Unable to save award record.', true);
            }
        }

        function renderAwardsDetailRows(rows) {
            if (!awardsDetailFilesBody) return;
            var list = Array.isArray(rows) ? rows : [];
            if (!list.length) {
                awardsDetailFilesBody.innerHTML = '<tr><td colspan="4" class="py-4 px-2 text-sm text-on-surface-variant">No records found for this title.</td></tr>';
                return;
            }
            awardsDetailFilesBody.innerHTML = '';
            list.forEach(function (item) {
                var tr = document.createElement('tr');
                tr.className = 'border-b border-outline-variant/10 last:border-b-0';
                var sourceText = normalizeAwardDetailValue(item.entry_source);
                var rankText = normalizeAwardDetailValue(item.entry_rank);
                var combined = [sourceText, rankText].filter(function (x) { return x; }).join(' | ');
                var yearText = (item.entry_year === null || item.entry_year === undefined || item.entry_year === '') ? '—' : String(item.entry_year);
                tr.innerHTML =
                    '<td class="py-3 px-2 text-sm font-semibold text-on-surface">' + escapeHtml(item.entry_title || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(combined || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(yearText) + '</td>' +
                    '<td class="py-3 px-2 text-sm">' +
                        '<div class="flex items-center gap-2">' +
                            '<button type="button" class="awards-detail-edit px-2 py-1 rounded-md bg-surface-container text-on-surface text-xs font-semibold hover:bg-surface-container-high" ' +
                                'data-detail-id="' + escapeHtml(item.id) + '" ' +
                                'data-indicator="' + escapeHtml(item.indicator_name || '') + '" ' +
                                'data-title="' + escapeHtml(item.entry_title || '') + '" ' +
                                'data-source="' + escapeHtml(item.entry_source || '') + '" ' +
                                'data-rank="' + escapeHtml(item.entry_rank || '') + '" ' +
                                'data-year="' + escapeHtml(item.entry_year == null ? '' : item.entry_year) + '">Edit</button>' +
                            '<button type="button" class="awards-detail-delete px-2 py-1 rounded-md bg-error-container text-on-error-container text-xs font-semibold hover:opacity-90" data-detail-id="' + escapeHtml(item.id) + '">Delete</button>' +
                        '</div>' +
                    '</td>';
                awardsDetailFilesBody.appendChild(tr);
            });
        }

        async function loadAwardsDetailRecords(indicatorLabel) {
            if (!awardsDetailFilesBody) return;
            if (awardsDetailTitle) awardsDetailTitle.textContent = indicatorLabel || 'Award Detail';
            awardsDetailFilesBody.innerHTML = '<tr><td colspan="4" class="py-4 px-2 text-sm text-on-surface-variant">Loading records…</td></tr>';
            try {
                var resp = await fetch('api/mobility-international-awards.php?action=details&indicator=' + encodeURIComponent(indicatorLabel || '') + '&ts=' + Date.now(), {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderAwardsDetailRows(result.data || []);
            } catch (_) {
                awardsDetailFilesBody.innerHTML = '<tr><td colspan="4" class="py-4 px-2 text-sm text-on-surface-variant">Unable to load records.</td></tr>';
            }
        }

        async function openAwardsDetailModal(rowEl) {
            if (!awardsDetailModal || !rowEl) return;
            var label = rowEl.getAttribute('data-award-label') || 'Award Detail';
            currentAwardsDetailIndicator = label;
            awardsDetailModal.classList.remove('hidden');
            awardsDetailModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            await loadAwardsDetailRecords(label);
        }

        function renderAwardsRows(rows) {
            if (!awardsTbody) return;
            awardsTbody.innerHTML = '';
            var list = Array.isArray(rows) ? rows : [];
            if (list.length === 0) {
                awardsTbody.innerHTML = '<tr id="awards-empty-row"><td colspan="8" class="px-6 py-8 text-center text-sm text-on-surface-variant">No awards records yet.</td></tr>';
                return;
            }

            list.forEach(function (row) {
                var dotClass = resolveAwardDotClass(row.color_token);
                var tr = document.createElement('tr');
                tr.className = 'hover:bg-surface-container-low transition-colors group cursor-pointer';
                tr.setAttribute('tabindex', '0');
                tr.setAttribute('role', 'button');
                tr.setAttribute('aria-label', 'Open details for ' + (row.indicator_name || 'award indicator'));
                tr.setAttribute('data-award-label', row.indicator_name || '');
                tr.setAttribute('data-award-2019', normalizeCounterValue(row.y2019));
                tr.setAttribute('data-award-2020', normalizeCounterValue(row.y2020));
                tr.setAttribute('data-award-2021', normalizeCounterValue(row.y2021));
                tr.setAttribute('data-award-2022', normalizeCounterValue(row.y2022));
                tr.setAttribute('data-award-2023', normalizeCounterValue(row.y2023));
                tr.setAttribute('data-award-2024', normalizeCounterValue(row.y2024));
                tr.setAttribute('data-award-2025', normalizeCounterValue(row.y2025_proj));
                tr.innerHTML =
                    '<td class="px-6 py-4">' +
                        '<div class="flex items-center gap-3">' +
                            '<div class="w-2 h-2 rounded-full ' + dotClass + '"></div>' +
                            '<span class="text-sm font-semibold text-on-surface">' + escapeHtml(row.indicator_name || '') + '</span>' +
                        '</div>' +
                    '</td>' +
                    '<td class="px-4 py-4 text-center text-sm font-medium">' + normalizeCounterValue(row.y2019) + '</td>' +
                    '<td class="px-4 py-4 text-center text-sm font-medium">' + normalizeCounterValue(row.y2020) + '</td>' +
                    '<td class="px-4 py-4 text-center text-sm font-medium">' + normalizeCounterValue(row.y2021) + '</td>' +
                    '<td class="px-4 py-4 text-center text-sm font-medium">' + normalizeCounterValue(row.y2022) + '</td>' +
                    '<td class="px-4 py-4 text-center text-sm font-medium">' + normalizeCounterValue(row.y2023) + '</td>' +
                    '<td class="px-4 py-4 text-center text-sm font-bold text-primary">' + normalizeCounterValue(row.y2024) + '</td>' +
                    '<td class="px-4 py-4 text-center text-sm font-medium">' + normalizeCounterValue(row.y2025_proj) + '</td>';
                awardsTbody.appendChild(tr);
            });
        }

        if (awardsTbody) {
            awardsTbody.addEventListener('click', function (e) {
                var row = e.target.closest('tr');
                if (!row || !row.hasAttribute('data-award-label')) return;
                openAwardsDetailModal(row);
            });
            awardsTbody.addEventListener('keydown', function (e) {
                var row = e.target.closest('tr');
                if (!row || !row.hasAttribute('data-award-label')) return;
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                openAwardsDetailModal(row);
            });
        }

        async function loadAwardsRecords() {
            if (!awardsTbody) return;
            awardsTbody.innerHTML = '<tr id="awards-loading-row"><td colspan="8" class="px-6 py-8 text-center text-sm text-on-surface-variant">Loading awards records...</td></tr>';
            try {
                var resp = await fetch('api/mobility-international-awards.php?ts=' + Date.now(), {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) {
                    throw new Error((result && result.error) ? result.error : 'Failed to load awards records');
                }
                renderAwardsRows(result.data || []);
            } catch (_) {
                renderAwardsRows([]);
            }
        }

        function setTabActive(btn) {
            document.querySelectorAll('.mobility-tab').forEach(function (b) {
                var on = b === btn;
                activeTabClasses.forEach(function (c) { b.classList.toggle(c, on); });
                inactiveTabClasses.forEach(function (c) { b.classList.toggle(c, !on); });
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            document.querySelectorAll('.mobility-tab-panel').forEach(function (p) { p.classList.add('hidden'); });
            var tab = btn.getAttribute('data-mobility-tab');
            var panelId = tab === 'awards' ? 'panel-awards' : tab === 'memberships' ? 'panel-memberships' : tab === 'linkages' ? 'panel-linkages' : tab === 'student-mobility' ? 'panel-student-mobility' : tab === 'scholarships' ? 'panel-scholarships' : tab === 'staff-mobility' ? 'panel-staff-mobility' : tab === 'full-time-foreign-students' ? 'panel-full-time-foreign-students' : tab === 'full-time-foreign-faculty' ? 'panel-full-time-foreign-faculty' : tab === 'internationalization-research' ? 'panel-internationalization-research' : tab === 'transnational-education-program' ? 'panel-transnational-education-program' : tab === 'collaborative-events-activities' ? 'panel-collaborative-events-activities' : tab === 'in-house-asean-internationalization-events' ? 'panel-in-house-asean-internationalization-events' : tab === 'international-sustainability-centers' ? 'panel-international-sustainability-centers' : tab === 'studyph-program' ? 'panel-studyph-program' : tab === 'coil-classes' ? 'panel-coil-classes' : 'panel-other';
            var panel = document.getElementById(panelId);
            if (panel) panel.classList.remove('hidden');
        }

        document.querySelectorAll('.mobility-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                try {
                    var t = btn.getAttribute('data-mobility-tab');
                    if (t) {
                        localStorage.setItem('mobility_active_tab', t);
                        // Persist via URL so refresh always keeps the same tab.
                        var url = new URL(window.location.href);
                        url.searchParams.set('mobilityTab', t);
                        window.history.replaceState({}, '', url.toString());
                    }
                } catch (_) {}
                setTabActive(btn);
            });
        });

        // Restore active tab after refresh.
        try {
            var url = new URL(window.location.href);
            var tabFromUrl = url.searchParams.get('mobilityTab');
            var savedTab = tabFromUrl || localStorage.getItem('mobility_active_tab');
            if (savedTab) {
                var savedBtn = document.querySelector('.mobility-tab[data-mobility-tab="' + savedTab + '"]');
                if (savedBtn) setTabActive(savedBtn);
                else {
                    var fallbackBtn = document.querySelector('.mobility-tab[data-mobility-tab="memberships"]');
                    if (fallbackBtn) setTabActive(fallbackBtn);
                }
            } else {
                var defaultBtn = document.querySelector('.mobility-tab[data-mobility-tab="memberships"]');
                if (defaultBtn) setTabActive(defaultBtn);
            }
        } catch (_) {}
        revealTabsPanelsSection();
        loadMobilityCounters();
        loadAwardsRecords();

        var editingMembershipId = null;
        var editingLinkageId = null;
        var editingLinkageSdgs = '';
        var editingStudentMobilityId = null;
        var editingScholarshipId = null;
        var editingStaffMobilityId = null;
        var editingFullTimeStudentId = null;
        var editingFullTimeFacultyId = null;
        var editingResearchId = null;
        var editingCoilId = null;
        var editingTnId = null;
        var editingCeaId = null;
        var editingInhouseId = null;
        var editingIscId = null;
        var editingStudyphId = null;

        function setMembershipClassification(typeValue) {
            var intlBtn = document.getElementById('classification-intl');
            var localBtn = document.getElementById('classification-local');
            if (!intlBtn || !localBtn) return;
            var isIntl = String(typeValue || '').trim().toLowerCase() === 'international';
            intlBtn.classList.toggle('bg-primary', isIntl);
            intlBtn.classList.toggle('text-on-primary', isIntl);
            intlBtn.classList.toggle('shadow-sm', isIntl);
            intlBtn.classList.toggle('text-on-surface-variant', !isIntl);
            intlBtn.classList.toggle('hover:bg-surface-container', !isIntl);
            localBtn.classList.toggle('bg-primary', !isIntl);
            localBtn.classList.toggle('text-on-primary', !isIntl);
            localBtn.classList.toggle('shadow-sm', !isIntl);
            localBtn.classList.toggle('text-on-surface-variant', isIntl);
            localBtn.classList.toggle('hover:bg-surface-container', isIntl);
        }

        function openMembershipModal() {
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var m = document.getElementById('membership-modal');
            if (!m) return;
            m.classList.remove('hidden');
            m.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('org_name');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function resetMembershipModalForm() {
            var orgEl = document.getElementById('org_name');
            var yearEl = document.getElementById('membership_year');
            var statusEl = document.getElementById('membership_status');
            var customStatusEl = document.getElementById('membership_status_custom');
            var intlBtn = document.getElementById('classification-intl');
            var localBtn = document.getElementById('classification-local');

            if (orgEl) orgEl.value = '';
            if (yearEl) yearEl.value = '';
            if (statusEl) statusEl.value = '';
            if (customStatusEl) customStatusEl.value = '';
            editingMembershipId = null;

            // Restore default classification selection.
            if (intlBtn && localBtn) setMembershipClassification('International');
        }

        function closeMembershipModal() {
            var m = document.getElementById('membership-modal');
            if (!m) return;
            m.classList.add('hidden');
            m.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetMembershipModalForm();
        }

        function openLinkagesModal() {
            closeMembershipModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var a = document.getElementById('linkages-entry-modal');
            if (!a) return;
            a.classList.remove('hidden');
            a.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('lp-partner-institution');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeLinkagesModal() {
            var a = document.getElementById('linkages-entry-modal');
            if (!a) return;
            a.classList.add('hidden');
            a.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetLinkagesModalForm();
        }

        function resetLinkagesModalForm() {
            var form = document.getElementById('linkages-entry-form');
            if (form && typeof form.reset === 'function') form.reset();

            editingLinkageId = null;
            editingLinkageSdgs = '';

            var titleEl = document.getElementById('linkages-entry-modal-title');
            if (titleEl) titleEl.textContent = 'Linkages & Partnerships';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Create a new editorial record for mobility cooperation.';
            var createBtn = document.getElementById('lp-btn-create-entry');
            if (createBtn) createBtn.textContent = 'Create Entry';
        }

        function openStudentMobilityModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeScholarshipsModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var s = document.getElementById('student-mobility-entry-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('student-partner-institution');
            if (first) setTimeout(function () { first.focus(); }, 50);
            if (!editingStudentMobilityId) {
                // Defaults for new records only.
                var scopeVal = document.getElementById('student-scope-value');
                if (scopeVal) scopeVal.value = 'inbound';
                var durationVal = document.getElementById('student-duration-value');
                if (durationVal) durationVal.value = 'short';
                updateStudentScopeUI(true);
                updateStudentDurationUI('short');
            }
        }

        function closeStudentMobilityModal() {
            var s = document.getElementById('student-mobility-entry-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetStudentMobilityModalForm();
        }

        function resetStudentMobilityModalForm() {
            var form = document.getElementById('student-mobility-entry-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingStudentMobilityId = null;

            var scopeVal = document.getElementById('student-scope-value');
            if (scopeVal) scopeVal.value = 'inbound';
            var durationVal = document.getElementById('student-duration-value');
            if (durationVal) durationVal.value = 'short';
            updateStudentScopeUI(true);
            updateStudentDurationUI('short');

            var titleEl = document.getElementById('student-mobility-entry-modal-title');
            if (titleEl) titleEl.textContent = 'Student Mobility & Internship Record';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Digital Curator • Institutional Data Entry';
            var saveBtn = document.getElementById('student-mobility-entry-save');
            if (saveBtn) saveBtn.textContent = 'Log Mobility';
        }

        function openScholarshipsModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var s = document.getElementById('scholarships-entry-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('scholarship-partner-institution');
            if (first) setTimeout(function () { first.focus(); }, 50);
            if (!editingScholarshipId) {
                var scopeVal = document.getElementById('scholarship-scope-value');
                if (scopeVal) scopeVal.value = 'inbound';
                updateScholarshipScopeUI(true);
                updateScholarshipModalityUI('on-site');
            }
        }

        function closeScholarshipsModal() {
            var s = document.getElementById('scholarships-entry-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetScholarshipsModalForm();
        }

        function resetScholarshipsModalForm() {
            var form = document.getElementById('scholarships-entry-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingScholarshipId = null;
            var scopeVal = document.getElementById('scholarship-scope-value');
            if (scopeVal) scopeVal.value = 'inbound';
            var modVal = document.getElementById('scholarship-modality-value');
            if (modVal) modVal.value = 'on-site';
            updateScholarshipScopeUI(true);
            updateScholarshipModalityUI('on-site');

            var titleEl = document.getElementById('scholarships-entry-modal-title');
            if (titleEl) titleEl.textContent = 'International Scholarships and Fellowships';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Registry of mobility initiatives and academic collaborations.';
            var saveBtn = document.getElementById('scholarships-entry-save');
            if (saveBtn) saveBtn.textContent = 'Save Entry';
        }

        function openStaffMobilityModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeFullTimeForeignStudentsModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var s = document.getElementById('staff-mobility-entry-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('staff-partner-institution');
            if (first) setTimeout(function () { first.focus(); }, 50);
            if (!editingStaffMobilityId) {
                updateStaffScopeUI(true);
                updateStaffModalityUI('physical');
            }
        }

        function closeStaffMobilityModal() {
            var s = document.getElementById('staff-mobility-entry-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetStaffMobilityModalForm();
        }

        function resetStaffMobilityModalForm() {
            var form = document.getElementById('staff-mobility-entry-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingStaffMobilityId = null;
            var scopeVal = document.getElementById('staff-scope-value');
            if (scopeVal) scopeVal.value = 'inbound';
            var modalityVal = document.getElementById('staff-modality-value');
            if (modalityVal) modalityVal.value = 'physical';
            updateStaffScopeUI(true);
            updateStaffModalityUI('physical');

            var titleEl = document.getElementById('staff-mobility-entry-modal-title');
            if (titleEl) titleEl.textContent = 'Staff Mobility and Scholarships';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Register a new international mobility record in the editorial system.';
            var saveBtn = document.getElementById('staff-mobility-entry-save');
            if (saveBtn) saveBtn.textContent = 'Save Mobility Record';
        }

        function openFullTimeForeignStudentsModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignFacultyModal();
            closeFullTimeStudentsDetailModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var s = document.getElementById('full-time-foreign-students-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('full-time-country');
            if (first) setTimeout(function () { first.focus(); }, 50);
            if (!editingFullTimeStudentId) {
                updateFullTimeModalityUI('on-site');
                updateFullTimeLevelUI('undergraduate');
            }
        }

        function closeFullTimeForeignStudentsModal() {
            var s = document.getElementById('full-time-foreign-students-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetFullTimeStudentsModalForm();
        }

        function resetFullTimeStudentsModalForm() {
            var form = document.getElementById('full-time-foreign-students-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingFullTimeStudentId = null;
            var modalityVal = document.getElementById('full-time-modality-value');
            if (modalityVal) modalityVal.value = 'on-site';
            var levelVal = document.getElementById('full-time-level-value');
            if (levelVal) levelVal.value = 'undergraduate';
            updateFullTimeModalityUI('on-site');
            updateFullTimeLevelUI('undergraduate');

            var titleEl = document.getElementById('full-time-foreign-students-title');
            if (titleEl) titleEl.textContent = 'Full-Time Foreign Students';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Add a new student mobility record to the editorial database.';
            var saveBtn = document.getElementById('full-time-foreign-students-save');
            if (saveBtn) saveBtn.textContent = 'Save Student Record';
        }

        function openFullTimeForeignFacultyModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeFacultyDetailModal();
            closeInternationalizationResearchModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var s = document.getElementById('full-time-foreign-faculty-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('full-time-faculty-name');
            if (first) setTimeout(function () { first.focus(); }, 50);
            if (!editingFullTimeFacultyId) {
                updateFullTimeFacultyScopeUI('inbound');
                updateFullTimeFacultyLevelUI('undergraduate');
            }
        }

        function closeFullTimeForeignFacultyModal() {
            var s = document.getElementById('full-time-foreign-faculty-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetFullTimeFacultyModalForm();
        }

        function resetFullTimeFacultyModalForm() {
            var form = document.getElementById('full-time-foreign-faculty-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingFullTimeFacultyId = null;
            var scopeVal = document.getElementById('full-time-faculty-scope-value');
            if (scopeVal) scopeVal.value = 'inbound';
            var levelVal = document.getElementById('full-time-faculty-level-value');
            if (levelVal) levelVal.value = 'undergraduate';
            updateFullTimeFacultyScopeUI('inbound');
            updateFullTimeFacultyLevelUI('undergraduate');

            var titleEl = document.getElementById('full-time-foreign-faculty-title');
            if (titleEl) titleEl.textContent = 'Full-Time Foreign Faculty';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Enroll a new academic record into the mobility program.';
            var saveBtn = document.getElementById('full-time-foreign-faculty-save');
            if (saveBtn) saveBtn.textContent = 'Save Faculty Record';
        }

        function openInternationalizationResearchModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeForeignFacultyModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            var s = document.getElementById('internationalization-research-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            if (!editingResearchId) {
                updateResearchCategoryUI('collaborative');
                updateResearchPublishedUI(true);
            }
        }

        function closeInternationalizationResearchModal() {
            var s = document.getElementById('internationalization-research-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetResearchModalForm();
        }

        function resetResearchModalForm() {
            var form = document.getElementById('internationalization-research-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingResearchId = null;
            updateResearchCategoryUI('collaborative');
            updateResearchPublishedUI(true);
            var fiscalYearEl = document.getElementById('research-fiscal-year');
            if (fiscalYearEl && fiscalYearEl.options && fiscalYearEl.options.length > 0) fiscalYearEl.selectedIndex = 0;
            var ongoing = document.querySelector('input[name="research-status"][value="ongoing"]');
            if (ongoing) ongoing.checked = true;

            var titleEl = document.getElementById('internationalization-research-title');
            if (titleEl) titleEl.textContent = 'Internationalization of Research';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Submit new research records for international collaboration and citations.';
            var saveBtn = document.getElementById('research-save-record');
            if (saveBtn) saveBtn.innerHTML = '<span class="material-symbols-outlined text-base">save</span>SAVE RESEARCH RECORD';
        }

        function openTransnationalEducationProgramModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeForeignFacultyModal();
            closeInternationalizationResearchModal();
            closeCoilModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();

            var s = document.getElementById('transnational-education-program-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            var first = document.getElementById('tn-partner-university');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeTransnationalEducationProgramModal() {
            var s = document.getElementById('transnational-education-program-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetTransnationalModalForm();
        }

        function resetTransnationalModalForm() {
            var form = document.getElementById('transnational-education-program-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingTnId = null;
            var titleEl = document.getElementById('transnational-education-program-modal-title');
            if (titleEl) titleEl.textContent = 'New Transnational Education Program';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Add a new transnational academic record to the editorial database.';
            var saveBtn = document.getElementById('tn-program-save');
            if (saveBtn) saveBtn.textContent = 'Save Program';
            var natWrap = document.getElementById('tn-nationalities');
            if (natWrap) {
                Array.prototype.slice.call(natWrap.querySelectorAll('.tn-nationality-chip')).forEach(function (el) { el.remove(); });
            }
        }

        function openCollaborativeEventsActivitiesModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeForeignFacultyModal();
            closeInternationalizationResearchModal();
            closeTransnationalEducationProgramModal();
            closeCoilModal();
            closeCollaborativeEventsActivitiesModal();

            var s = document.getElementById('collaborative-events-activities-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            var first = document.getElementById('cea-event-name');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeCollaborativeEventsActivitiesModal() {
            var s = document.getElementById('collaborative-events-activities-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetCollaborativeEventsModalForm();
        }

        function resetCollaborativeEventsModalForm() {
            var form = document.getElementById('collaborative-events-activities-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingCeaId = null;
            var partnersWrap = document.getElementById('cea-partners');
            if (partnersWrap) {
                Array.prototype.slice.call(partnersWrap.querySelectorAll('.cea-partner-chip')).forEach(function (chip) { chip.remove(); });
            }
            var participantsEl = document.getElementById('cea-participants');
            if (participantsEl) participantsEl.value = '0';
            var titleEl = document.getElementById('collaborative-events-activities-modal-title');
            if (titleEl) titleEl.textContent = 'Collaborative Event/Activity';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Add a collaborative event/activity record (name, partners, countries, year, participants).';
            var saveBtn = document.getElementById('cea-save');
            if (saveBtn) saveBtn.textContent = 'Save Activity';
        }

        function openInHouseAseanModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeForeignFacultyModal();
            closeInternationalizationResearchModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            closeCoilModal();
            closeInHouseAseanModal();

            var s = document.getElementById('in-house-asean-internationalization-events-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            var first = document.getElementById('inhouse-event-name');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeInHouseAseanModal() {
            var s = document.getElementById('in-house-asean-internationalization-events-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetInHouseModalForm();
        }

        function resetInHouseModalForm() {
            var form = document.getElementById('in-house-asean-internationalization-events-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingInhouseId = null;
            var partnersWrap = document.getElementById('inhouse-partners');
            if (partnersWrap) Array.prototype.slice.call(partnersWrap.querySelectorAll('.inhouse-partner-chip')).forEach(function (chip) { chip.remove(); });
            var countriesWrap = document.getElementById('inhouse-countries');
            if (countriesWrap) Array.prototype.slice.call(countriesWrap.querySelectorAll('.inhouse-country-chip')).forEach(function (chip) { chip.remove(); });
            var participantsEl = document.getElementById('inhouse-participants');
            if (participantsEl) participantsEl.value = '0';
            var titleEl = document.getElementById('in-house-asean-internationalization-events-modal-title');
            if (titleEl) titleEl.textContent = 'New In-house Event';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Record new institutional internationalization activities.';
            var saveBtn = document.getElementById('inhouse-save');
            if (saveBtn) saveBtn.textContent = 'Save Event';
        }

        function openInternationalSustainabilityCentersModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeForeignFacultyModal();
            closeInternationalizationResearchModal();
            closeTransnationalEducationProgramModal();
            closeCoilModal();
            closeCollaborativeEventsActivitiesModal();
            closeInHouseAseanModal();
            closeInternationalSustainabilityCentersModal();

            var s = document.getElementById('international-sustainability-centers-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            var first = document.getElementById('isc-center-name');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeInternationalSustainabilityCentersModal() {
            var s = document.getElementById('international-sustainability-centers-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetIscModalForm();
        }

        function resetIscModalForm() {
            var form = document.getElementById('international-sustainability-centers-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingIscId = null;
            var partnersWrap = document.getElementById('isc-partners');
            if (partnersWrap) Array.prototype.slice.call(partnersWrap.querySelectorAll('.isc-partner-chip')).forEach(function (chip) { chip.remove(); });
            var countriesWrap = document.getElementById('isc-countries');
            if (countriesWrap) Array.prototype.slice.call(countriesWrap.querySelectorAll('.isc-country-chip')).forEach(function (chip) { chip.remove(); });
            var titleEl = document.getElementById('international-sustainability-centers-modal-title');
            if (titleEl) titleEl.textContent = 'Register Sustainability Center';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'International centers categorized under global sustainability networks.';
            var saveBtn = document.getElementById('isc-save');
            if (saveBtn) saveBtn.textContent = 'Save Center';
        }

        function openStudyPHProgramModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeForeignFacultyModal();
            closeInternationalizationResearchModal();
            closeTransnationalEducationProgramModal();
            closeCoilModal();
            closeCollaborativeEventsActivitiesModal();
            closeInHouseAseanModal();
            closeInternationalSustainabilityCentersModal();
            closeStudyPHProgramModal();

            var s = document.getElementById('studyph-program-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');

            var first = document.getElementById('studyph-year');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeStudyPHProgramModal() {
            var s = document.getElementById('studyph-program-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetStudyphModalForm();
        }

        function resetStudyphModalForm() {
            var form = document.getElementById('studyph-program-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingStudyphId = null;
            var sdgInput = document.getElementById('studyph-sdgs-value');
            if (sdgInput) sdgInput.value = '';
            var pillsWrap = document.getElementById('studyph-sdg-pills');
            if (pillsWrap) {
                Array.prototype.slice.call(pillsWrap.querySelectorAll('.studyph-sdg-pill')).forEach(function (pill, idx) {
                    var active = idx === 0;
                    pill.classList.toggle('border-primary/20', active);
                    pill.classList.toggle('text-primary', active);
                    pill.classList.toggle('border-outline-variant/30', !active);
                    pill.classList.toggle('text-on-surface-variant', !active);
                });
                var first = pillsWrap.querySelector('.studyph-sdg-pill');
                if (first && sdgInput) sdgInput.value = first.getAttribute('data-value') || '';
            }
            var titleEl = document.getElementById('studyph-program-modal-title');
            if (titleEl) titleEl.textContent = 'Add StudyPH Program Entry';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Populate the editorial database with a new StudyPH program record.';
            var saveBtn = document.getElementById('studyph-save');
            if (saveBtn) saveBtn.textContent = 'Save Program';
        }

        function openCoilModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
            closeFullTimeForeignFacultyModal();
            closeInternationalizationResearchModal();
            closeTransnationalEducationProgramModal();
            closeCollaborativeEventsActivitiesModal();
            closeCoilModal();
            var s = document.getElementById('coil-entry-modal');
            if (!s) return;
            s.classList.remove('hidden');
            s.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            var first = document.getElementById('coil-partner-university');
            if (first) setTimeout(function () { first.focus(); }, 50);
        }

        function closeCoilModal() {
            var s = document.getElementById('coil-entry-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            resetCoilModalForm();
        }

        function resetCoilModalForm() {
            var form = document.getElementById('coil-entry-form');
            if (form && typeof form.reset === 'function') form.reset();
            editingCoilId = null;
            var titleEl = document.getElementById('coil-entry-modal-title');
            if (titleEl) titleEl.textContent = 'New COIL Class';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Add a new Collaborative Online International Learning record to the editorial database.';
            var saveBtn = document.getElementById('coil-entry-save');
            if (saveBtn) saveBtn.textContent = 'Save Record';
        }

        function updateStudentScopeUI(isInbound) {
            var inboundBtn = document.getElementById('student-scope-inbound');
            var outboundBtn = document.getElementById('student-scope-outbound');
            if (!inboundBtn || !outboundBtn) return;

            inboundBtn.classList.toggle('bg-primary', isInbound);
            inboundBtn.classList.toggle('text-on-primary', isInbound);
            inboundBtn.classList.toggle('shadow-sm', isInbound);
            inboundBtn.classList.toggle('text-on-surface-variant', !isInbound);
            outboundBtn.classList.toggle('bg-primary', !isInbound);
            outboundBtn.classList.toggle('text-on-primary', !isInbound);
            outboundBtn.classList.toggle('shadow-sm', !isInbound);
            outboundBtn.classList.toggle('text-on-surface-variant', isInbound);
        }

        function updateStudentDurationUI(duration) {
            var shortBtn = document.getElementById('student-duration-short');
            var longBtn = document.getElementById('student-duration-long');
            if (!shortBtn || !longBtn) return;

            var isShort = duration === 'short';
            shortBtn.classList.toggle('bg-primary', isShort);
            shortBtn.classList.toggle('text-on-primary', isShort);
            longBtn.classList.toggle('bg-primary', !isShort);
            longBtn.classList.toggle('text-on-primary', !isShort);

            // Label color (we hardcoded text-on-surface in markup)
            var shortLabel = shortBtn.querySelector('span.font-bold');
            var longLabel = longBtn.querySelector('span.font-bold');
            if (shortLabel) {
                shortLabel.classList.toggle('text-on-primary', isShort);
                shortLabel.classList.toggle('text-on-surface', !isShort);
            }
            if (longLabel) {
                longLabel.classList.toggle('text-on-primary', !isShort);
                longLabel.classList.toggle('text-on-surface', isShort);
            }

            // Dot indicator
            shortBtn.querySelectorAll('.student-duration-dot').forEach(function (dot) {
                dot.classList.toggle('opacity-100', isShort);
                dot.classList.toggle('opacity-0', !isShort);
            });
            longBtn.querySelectorAll('.student-duration-dot').forEach(function (dot) {
                dot.classList.toggle('opacity-100', !isShort);
                dot.classList.toggle('opacity-0', isShort);
            });

            var durationVal = document.getElementById('student-duration-value');
            if (durationVal) durationVal.value = duration;
        }

        function updateScholarshipScopeUI(isInbound) {
            var inboundBtn = document.getElementById('scholarship-scope-inbound');
            var outboundBtn = document.getElementById('scholarship-scope-outbound');
            if (!inboundBtn || !outboundBtn) return;

            inboundBtn.classList.toggle('bg-primary', isInbound);
            inboundBtn.classList.toggle('text-on-primary', isInbound);
            inboundBtn.classList.toggle('shadow-sm', isInbound);
            inboundBtn.classList.toggle('bg-surface-container-high', !isInbound);
            inboundBtn.classList.toggle('text-on-surface-variant', !isInbound);
            inboundBtn.classList.toggle('hover:bg-surface-container', !isInbound);

            outboundBtn.classList.toggle('bg-primary', !isInbound);
            outboundBtn.classList.toggle('text-on-primary', !isInbound);
            outboundBtn.classList.toggle('shadow-sm', !isInbound);
            outboundBtn.classList.toggle('bg-surface-container-high', isInbound);
            outboundBtn.classList.toggle('text-on-surface-variant', isInbound);
            outboundBtn.classList.toggle('hover:bg-surface-container', isInbound);

            var v = document.getElementById('scholarship-scope-value');
            if (v) v.value = isInbound ? 'inbound' : 'outbound';
        }

        function updateScholarshipModalityUI(modality) {
            var ids = ['on-site', 'virtual', 'hybrid'];
            var map = {
                'on-site': document.getElementById('scholarship-modality-onsite'),
                'virtual': document.getElementById('scholarship-modality-virtual'),
                'hybrid': document.getElementById('scholarship-modality-hybrid')
            };
            ids.forEach(function (id) {
                var btn = map[id];
                if (!btn) return;
                var on = id === modality;
                btn.classList.toggle('bg-surface-container-high', on);
                btn.classList.toggle('text-primary', on);
                btn.classList.toggle('border-primary/20', on);
                btn.classList.toggle('bg-surface-container-low', !on);
                btn.classList.toggle('text-on-surface-variant', !on);
                btn.classList.toggle('border-transparent', !on);
            });
            var v = document.getElementById('scholarship-modality-value');
            if (v) v.value = modality;
        }

        function updateStaffScopeUI(isInbound) {
            var inboundBtn = document.getElementById('staff-scope-inbound');
            var outboundBtn = document.getElementById('staff-scope-outbound');
            if (!inboundBtn || !outboundBtn) return;

            inboundBtn.classList.toggle('bg-primary', isInbound);
            inboundBtn.classList.toggle('text-on-primary', isInbound);
            inboundBtn.classList.toggle('shadow-sm', isInbound);
            inboundBtn.classList.toggle('bg-surface-container-high', !isInbound);
            inboundBtn.classList.toggle('text-on-surface-variant', !isInbound);
            inboundBtn.classList.toggle('hover:bg-surface-container', !isInbound);

            outboundBtn.classList.toggle('bg-primary', !isInbound);
            outboundBtn.classList.toggle('text-on-primary', !isInbound);
            outboundBtn.classList.toggle('shadow-sm', !isInbound);
            outboundBtn.classList.toggle('bg-surface-container-high', isInbound);
            outboundBtn.classList.toggle('text-on-surface-variant', isInbound);
            outboundBtn.classList.toggle('hover:bg-surface-container', isInbound);

            var v = document.getElementById('staff-scope-value');
            if (v) v.value = isInbound ? 'inbound' : 'outbound';
        }

        function updateStaffModalityUI(modality) {
            var map = {
                'physical': document.getElementById('staff-modality-physical'),
                'virtual': document.getElementById('staff-modality-virtual'),
                'hybrid': document.getElementById('staff-modality-hybrid')
            };
            Object.keys(map).forEach(function (key) {
                var btn = map[key];
                if (!btn) return;
                var on = key === modality;
                btn.classList.toggle('bg-surface-container-high', on);
                btn.classList.toggle('text-primary', on);
                btn.classList.toggle('border-primary/20', on);
                btn.classList.toggle('bg-surface-container-low', !on);
                btn.classList.toggle('text-on-surface-variant', !on);
                btn.classList.toggle('border-transparent', !on);
            });
            var v = document.getElementById('staff-modality-value');
            if (v) v.value = modality;
        }

        function updateFullTimeModalityUI(modality) {
            var map = {
                'on-site': document.getElementById('full-time-modality-onsite'),
                'virtual': document.getElementById('full-time-modality-virtual'),
                'hybrid': document.getElementById('full-time-modality-hybrid')
            };
            Object.keys(map).forEach(function (key) {
                var btn = map[key];
                if (!btn) return;
                var on = key === modality;
                btn.classList.toggle('bg-white', on);
                btn.classList.toggle('shadow-sm', on);
                btn.classList.toggle('text-primary', on);
                btn.classList.toggle('font-bold', on);
                btn.classList.toggle('text-on-surface-variant', !on);
                btn.classList.toggle('font-medium', !on);
            });
            var v = document.getElementById('full-time-modality-value');
            if (v) v.value = modality;
        }

        function updateFullTimeLevelUI(level) {
            var under = document.getElementById('full-time-level-undergraduate');
            var grad = document.getElementById('full-time-level-graduate');
            if (!under || !grad) return;
            var isUnder = level === 'undergraduate';

            under.classList.toggle('bg-white', isUnder);
            under.classList.toggle('shadow-sm', isUnder);
            under.classList.toggle('text-primary', isUnder);
            under.classList.toggle('font-bold', isUnder);
            under.classList.toggle('text-on-surface-variant', !isUnder);
            under.classList.toggle('font-medium', !isUnder);

            grad.classList.toggle('bg-white', !isUnder);
            grad.classList.toggle('shadow-sm', !isUnder);
            grad.classList.toggle('text-primary', !isUnder);
            grad.classList.toggle('font-bold', !isUnder);
            grad.classList.toggle('text-on-surface-variant', isUnder);
            grad.classList.toggle('font-medium', isUnder);

            var v = document.getElementById('full-time-level-value');
            if (v) v.value = level;
        }

        function updateFullTimeFacultyScopeUI(scope) {
            var inboundBtn = document.getElementById('full-time-faculty-scope-inbound');
            var outboundBtn = document.getElementById('full-time-faculty-scope-outbound');
            if (!inboundBtn || !outboundBtn) return;
            var isInbound = scope === 'inbound';

            inboundBtn.classList.toggle('bg-white', isInbound);
            inboundBtn.classList.toggle('shadow-sm', isInbound);
            inboundBtn.classList.toggle('text-primary', isInbound);
            inboundBtn.classList.toggle('font-bold', isInbound);
            inboundBtn.classList.toggle('text-on-surface-variant', !isInbound);
            inboundBtn.classList.toggle('font-medium', !isInbound);

            outboundBtn.classList.toggle('bg-white', !isInbound);
            outboundBtn.classList.toggle('shadow-sm', !isInbound);
            outboundBtn.classList.toggle('text-primary', !isInbound);
            outboundBtn.classList.toggle('font-bold', !isInbound);
            outboundBtn.classList.toggle('text-on-surface-variant', isInbound);
            outboundBtn.classList.toggle('font-medium', isInbound);

            var v = document.getElementById('full-time-faculty-scope-value');
            if (v) v.value = scope;
        }

        function updateFullTimeFacultyLevelUI(level) {
            var under = document.getElementById('full-time-faculty-level-undergraduate');
            var grad = document.getElementById('full-time-faculty-level-graduate');
            if (!under || !grad) return;
            var isUnder = level === 'undergraduate';

            under.classList.toggle('bg-white', isUnder);
            under.classList.toggle('shadow-sm', isUnder);
            under.classList.toggle('text-primary', isUnder);
            under.classList.toggle('font-bold', isUnder);
            under.classList.toggle('text-on-surface-variant', !isUnder);
            under.classList.toggle('font-medium', !isUnder);

            grad.classList.toggle('bg-white', !isUnder);
            grad.classList.toggle('shadow-sm', !isUnder);
            grad.classList.toggle('text-primary', !isUnder);
            grad.classList.toggle('font-bold', !isUnder);
            grad.classList.toggle('text-on-surface-variant', isUnder);
            grad.classList.toggle('font-medium', isUnder);

            var v = document.getElementById('full-time-faculty-level-value');
            if (v) v.value = level;
        }

        function updateResearchCategoryUI(category) {
            var ids = ['collaborative', 'sole', 'published', 'citations'];
            ids.forEach(function (id) {
                var btn = document.getElementById('research-cat-' + id);
                if (!btn) return;
                var on = id === category;
                btn.classList.toggle('bg-primary', on);
                btn.classList.toggle('text-on-primary', on);
                btn.classList.toggle('shadow-sm', on);
                btn.classList.toggle('text-on-surface-variant', !on);
                btn.classList.toggle('hover:bg-surface-container', !on);
            });
            var v = document.getElementById('research-category-value');
            if (v) v.value = category;
        }

        function updateResearchPublishedUI(isPublished) {
            var toggle = document.getElementById('research-published-toggle');
            var knob = document.getElementById('research-published-knob');
            if (!toggle || !knob) return;
            toggle.classList.toggle('bg-primary-container', isPublished);
            toggle.classList.toggle('bg-outline-variant', !isPublished);
            knob.classList.toggle('translate-x-5', isPublished);
            knob.classList.toggle('translate-x-0.5', !isPublished);
            var v = document.getElementById('research-published-value');
            if (v) v.value = isPublished ? 'published' : 'not-published';
        }

        var addBtn = document.getElementById('btn-add-membership-record');
        if (addBtn) addBtn.addEventListener('click', openMembershipModal);

        var addAwardBtn = document.getElementById('btn-add-award-record');
        if (addAwardBtn) {
            addAwardBtn.addEventListener('click', openAwardsAddModal);
        }
        document.querySelectorAll('.awards-add-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tabName = btn.getAttribute('data-awards-add-tab');
                if (!tabName) return;
                setAwardsAddTab(tabName);
            });
        });
        var awardsAddCloseBtn = document.getElementById('awards-add-modal-close');
        if (awardsAddCloseBtn) awardsAddCloseBtn.addEventListener('click', closeAwardsAddModal);
        var awardsAddCancelBtn = document.getElementById('awards-add-modal-cancel');
        if (awardsAddCancelBtn) awardsAddCancelBtn.addEventListener('click', closeAwardsAddModal);
        var awardsAddBackdrop = document.getElementById('awards-add-modal-backdrop');
        if (awardsAddBackdrop) awardsAddBackdrop.addEventListener('click', closeAwardsAddModal);
        var awardsAddSaveBtn = document.getElementById('awards-add-modal-save');
        if (awardsAddSaveBtn) awardsAddSaveBtn.addEventListener('click', saveAwardsAddRecord);
        if (awardsDetailFilesBody) {
            awardsDetailFilesBody.addEventListener('click', async function (e) {
                var editBtn = e.target.closest('.awards-detail-edit');
                if (editBtn) {
                    var detailItem = {
                        id: Number(editBtn.getAttribute('data-detail-id') || '0'),
                        indicator_name: editBtn.getAttribute('data-indicator') || '',
                        entry_title: editBtn.getAttribute('data-title') || '',
                        entry_source: editBtn.getAttribute('data-source') || '',
                        entry_rank: editBtn.getAttribute('data-rank') || '',
                        entry_year: editBtn.getAttribute('data-year') || ''
                    };
                    openAwardsAddModal(detailItem);
                    return;
                }

                var deleteBtn = e.target.closest('.awards-detail-delete');
                if (!deleteBtn) return;
                var detailId = Number(deleteBtn.getAttribute('data-detail-id') || '0');
                if (!detailId) return;
                if (!window.confirm('Delete this award record?')) return;

                var form = new FormData();
                form.append('detail_id', String(detailId));
                try {
                    var resp = await fetch('api/mobility-international-awards.php?action=delete_detail', {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin'
                    });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) {
                        throw new Error((result && result.error) ? result.error : 'Failed to delete record');
                    }
                    await loadAwardsRecords();
                    if (currentAwardsDetailIndicator) {
                        await loadAwardsDetailRecords(currentAwardsDetailIndicator);
                    }
                    showAwardsNotice('Award record deleted successfully.', false);
                } catch (err) {
                    showAwardsNotice((err && err.message) ? err.message : 'Unable to delete award record.', true);
                }
            });
        }
        var awardsDetailCloseBtn = document.getElementById('awards-detail-modal-close');
        if (awardsDetailCloseBtn) awardsDetailCloseBtn.addEventListener('click', closeAwardsDetailModal);
        var awardsDetailBackdrop = document.getElementById('awards-detail-modal-backdrop');
        if (awardsDetailBackdrop) awardsDetailBackdrop.addEventListener('click', closeAwardsDetailModal);

        var addLinkagesBtn = document.getElementById('btn-add-linkage-record');
        if (addLinkagesBtn) addLinkagesBtn.addEventListener('click', openLinkagesModal);

        var addStudentBtn = document.getElementById('btn-add-student-mobility-record');
        if (addStudentBtn) addStudentBtn.addEventListener('click', openStudentMobilityModal);

        var addScholarshipBtn = document.getElementById('btn-add-scholarship-record');
        if (addScholarshipBtn) addScholarshipBtn.addEventListener('click', openScholarshipsModal);

        var addStaffBtn = document.getElementById('btn-add-staff-mobility-record');
        if (addStaffBtn) addStaffBtn.addEventListener('click', openStaffMobilityModal);
        var addFullTimeBtn = document.getElementById('btn-add-full-time-foreign-student-record');
        if (addFullTimeBtn) addFullTimeBtn.addEventListener('click', openFullTimeForeignStudentsModal);
        var addFullTimeFacultyBtn = document.getElementById('btn-add-full-time-foreign-faculty-record');
        if (addFullTimeFacultyBtn) addFullTimeFacultyBtn.addEventListener('click', openFullTimeForeignFacultyModal);
        var addResearchBtn = document.getElementById('btn-add-internationalization-research-record');
        if (addResearchBtn) addResearchBtn.addEventListener('click', openInternationalizationResearchModal);

        var addTransBtn = document.getElementById('btn-add-transnational-education-program-record');
        if (addTransBtn) addTransBtn.addEventListener('click', openTransnationalEducationProgramModal);

        var addCollabBtn = document.getElementById('btn-add-collaborative-events-activities-record');
        if (addCollabBtn) addCollabBtn.addEventListener('click', openCollaborativeEventsActivitiesModal);

        var addInhouseBtn = document.getElementById('btn-add-in-house-asean-internationalization-event-record');
        if (addInhouseBtn) addInhouseBtn.addEventListener('click', openInHouseAseanModal);

        var addCentersBtn = document.getElementById('btn-add-international-sustainability-center-record');
        if (addCentersBtn) addCentersBtn.addEventListener('click', openInternationalSustainabilityCentersModal);

        var addStudyPHBtn = document.getElementById('btn-add-studyph-program-record');
        if (addStudyPHBtn) addStudyPHBtn.addEventListener('click', openStudyPHProgramModal);

        var addCoilBtn = document.getElementById('btn-add-coil-record');
        if (addCoilBtn) addCoilBtn.addEventListener('click', openCoilModal);

        var closeBtn = document.getElementById('membership-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', closeMembershipModal);
        var discardBtn = document.getElementById('membership-modal-discard');
        if (discardBtn) discardBtn.addEventListener('click', closeMembershipModal);
        var backdropEl = document.getElementById('membership-modal-backdrop');
        if (backdropEl) backdropEl.addEventListener('click', closeMembershipModal);
        var statusSelectEl = document.getElementById('membership_status');
        var customStatusInputEl = document.getElementById('membership_status_custom');
        if (statusSelectEl && customStatusInputEl) {
            statusSelectEl.addEventListener('change', function () {
                if ((statusSelectEl.value || '').trim() !== '') {
                    customStatusInputEl.value = '';
                }
            });
            customStatusInputEl.addEventListener('input', function () {
                if ((customStatusInputEl.value || '').trim() !== '') {
                    statusSelectEl.value = '';
                }
            });
        }
        var saveBtn = document.getElementById('membership-modal-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', async function () {
                var orgEl = document.getElementById('org_name');
                var yearEl = document.getElementById('membership_year');
                var statusEl = document.getElementById('membership_status');
                var customStatusEl = document.getElementById('membership_status_custom');
                var intlBtn = document.getElementById('classification-intl');

                var org = orgEl ? (orgEl.value || '').trim() : '';
                var yearVal = yearEl ? (yearEl.value || '').toString().trim() : '';
                var selectedStatus = statusEl ? (statusEl.value || '').trim() : '';
                var customStatus = customStatusEl ? (customStatusEl.value || '').trim() : '';
                var status = customStatus || selectedStatus;

                // Classification buttons toggle the styling; use that as the source of truth.
                var type = (intlBtn && intlBtn.classList && intlBtn.classList.contains('bg-primary')) ? 'International' : 'Local';

                var toastMembership = function (message, isError) {
                    var el = document.createElement('div');
                    el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                        (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                    el.textContent = message;
                    document.body.appendChild(el);
                    setTimeout(function () {
                        if (el && el.parentNode) el.parentNode.removeChild(el);
                    }, 3000);
                };

                var parseMembershipYear = function (rawYear) {
                    var raw = (rawYear || '').trim();
                    if (raw === '') {
                        return { year: null, yearEnd: null };
                    }
                    var single = raw.match(/^(\d{4})$/);
                    if (single) {
                        return { year: parseInt(single[1], 10), yearEnd: null };
                    }

                    // Accept ranges like "2024-2029" or "2024 – 2029".
                    var range = raw.match(/^(\d{4})\s*[-–—]\s*(\d{4})$/);
                    if (range) {
                        var start = parseInt(range[1], 10);
                        var end = parseInt(range[2], 10);
                        if (!isNaN(start) && !isNaN(end) && end >= start) {
                            return { year: start, yearEnd: end };
                        }
                    }

                    return null;
                };

                var parsedYear = parseMembershipYear(yearVal);
                var yearNum = parsedYear ? parsedYear.year : NaN;
                var yearEndNum = parsedYear ? parsedYear.yearEnd : null;
                if (!org) {
                    toastMembership('Please complete Organization.', true);
                    return;
                }
                if (!parsedYear) {
                    toastMembership('Please complete Organization and Year (YYYY or YYYY-YYYY).', true);
                    return;
                }
                if (yearNum !== null && (isNaN(yearNum) || yearNum < 1900 || yearNum > 2100)) {
                    toastMembership('Please provide a valid year (1900-2100).', true);
                    return;
                }
                if (yearEndNum !== null && (yearNum === null || isNaN(yearEndNum) || yearEndNum < yearNum || yearEndNum > 2100)) {
                    toastMembership('Please provide a valid year range (end year must be >= start year).', true);
                    return;
                }
                if (status.length > 100) {
                    toastMembership('Status is too long (max 100 characters).', true);
                    return;
                }

                try {
                    var fd = new FormData();
                    fd.append('org_name', org);
                    fd.append('membership_type', type);
                    fd.append('membership_status', status);
                    fd.append('membership_year', yearNum === null ? '' : String(yearNum));
                    fd.append('membership_year_end', yearEndNum === null ? '' : String(yearEndNum));
                    if (editingMembershipId) {
                        fd.append('id', String(editingMembershipId));
                    }

                    var resp = await fetch('api/mobility-memberships.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) {
                        throw new Error((result && result.error) ? result.error : 'Failed to save membership');
                    }

                    if (typeof loadMemberships === 'function') {
                        await loadMemberships();
                    }
                    toastMembership(result.message || 'Membership saved successfully.', false);
                } catch (err) {
                    toastMembership(err && err.message ? err.message : 'Failed to save membership', true);
                    return;
                }

                closeMembershipModal();
            });
        }

        // Linkages modal: persist entry to backend (uses existing MOU/MOA table).
        async function saveLinkagesToDatabase(opts) {
            opts = opts || {};
            var isDraft = !!opts.isDraft;

            var institutionEl = document.getElementById('lp-partner-institution');
            var typeOfPartnerEl = document.getElementById('lp-type-of-partner');
            var countryEl = document.getElementById('lp-country');
            var agreementEl = document.getElementById('lp-type-of-agreement');
            var fieldEl = document.getElementById('lp-field');
            var dateSignedEl = document.getElementById('lp-date-signed');
            var endDateEl = document.getElementById('lp-end-date');

            var institution = institutionEl ? (institutionEl.value || '').trim() : '';
            var location = countryEl ? (countryEl.value || '').trim() : '';
            var signDate = dateSignedEl ? (dateSignedEl.value || '').trim() : '';

            var endDate = endDateEl ? (endDateEl.value || '').trim() : '';
            if (isDraft) endDate = '';

            var agreement = agreementEl ? (agreementEl.value || '').trim() : '';
            var partnerType = typeOfPartnerEl ? (typeOfPartnerEl.value || '').trim() : '';
            var field = fieldEl ? (fieldEl.value || '').trim() : '';
            var sdgs = (editingLinkageSdgs || '').trim();

            var toast = function (message, isError) {
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                    (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () {
                    if (el && el.parentNode) el.parentNode.removeChild(el);
                }, 3000);
            };

            if (!institution || !location || !signDate) {
                toast('Please complete Partner Institution, Country, and Date Signed.', true);
                throw new Error('Validation failed');
            }

            var formData = new FormData();
            formData.append('institution', institution);
            formData.append('location', location);
            formData.append('sign_date', signDate);

            if (endDate) formData.append('end_date', endDate);
            if (partnerType) formData.append('partner', partnerType);
            if (agreement) formData.append('category', agreement); // API maps category -> type in DB
            var descriptionParts = [];
            if (field) descriptionParts.push('Field: ' + field);
            if (sdgs) descriptionParts.push('UN SDGs: ' + sdgs);
            if (descriptionParts.length > 0) formData.append('description', descriptionParts.join(' | '));
            if (agreement) formData.append('title', agreement);

            var endpoint = editingLinkageId
                ? ('api/mou-moa.php?action=update&id=' + encodeURIComponent(String(editingLinkageId)))
                : 'api/mou-moa.php';
            var response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = null;
            try {
                result = await response.json();
            } catch (e) {
                // Avoid JSON parse crashes when the backend returns HTML or empty body.
                toast('Server returned an unexpected response.', true);
                throw new Error('Invalid JSON response');
            }

            if (!response.ok || !result || !result.success) {
                var err = (result && result.error) ? result.error : 'Failed to save entry';
                toast(err, true);
                throw new Error(err);
            }

            if (typeof loadLinkages === 'function') {
                try { await loadLinkages(); } catch (_) {}
            }
            toast(result.message || 'Entry saved successfully.', false);
            return result;
        }

        var linkagesTbody = document.getElementById('linkages-table-body');

        function formatLinkagesDate(dateValue) {
            if (dateValue === null || dateValue === undefined || String(dateValue).trim() === '') return '—';
            var d = new Date(dateValue);
            if (!isNaN(d.getTime())) {
                return d.toISOString().slice(0, 10);
            }
            return String(dateValue);
        }

        function parseLinkagesDescriptionMeta(descriptionValue) {
            var raw = (descriptionValue || '').toString().trim();
            var out = { field: '', sdgs: '' };
            if (!raw) return out;

            var parts = raw.split('|').map(function (p) { return (p || '').trim(); }).filter(Boolean);
            parts.forEach(function (part) {
                var lower = part.toLowerCase();
                if (lower.indexOf('field:') === 0) {
                    var fieldVal = part.slice(part.indexOf(':') + 1).trim();
                    if (fieldVal) out.field = fieldVal;
                    return;
                }
                if (lower.indexOf('un sdgs:') === 0) {
                    var sdgVal = part.slice(part.indexOf(':') + 1).trim();
                    if (sdgVal) out.sdgs = sdgVal;
                }
            });

            if (!out.field && !out.sdgs && raw) {
                out.field = raw;
            }
            return out;
        }

        function escapeHtml(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderLinkages(entries) {
            if (!linkagesTbody) return;
            linkagesTbody.innerHTML = '';

            var rows = entries || [];
            if (rows.length === 0) {
                var emptyTr = document.createElement('tr');
                emptyTr.innerHTML = '<td colspan="9" class="py-10 text-center text-on-surface-variant text-sm">No linkage records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                linkagesTbody.appendChild(emptyTr);
                return;
            }

            rows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';

                var institution = r.institution || '—';
                var partnerType = r.partner || '—';
                var country = r.location || '—';
                var agreementType = r.type || r.category || '—';
                var parsedMeta = parseLinkagesDescriptionMeta(r.description);
                var signDate = formatLinkagesDate(r.sign_date);
                var endDate = formatLinkagesDate(r.end_date);
                tr.setAttribute('data-linkage-id', String(r.id || ''));
                tr.setAttribute('data-linkage-institution', institution);
                tr.setAttribute('data-linkage-partner', r.partner || '');
                tr.setAttribute('data-linkage-country', r.location || '');
                tr.setAttribute('data-linkage-agreement', agreementType === '—' ? '' : agreementType);
                tr.setAttribute('data-linkage-field', parsedMeta.field || '');
                tr.setAttribute('data-linkage-sdgs', parsedMeta.sdgs || '');
                tr.setAttribute('data-linkage-sign-date', r.sign_date || '');
                tr.setAttribute('data-linkage-end-date', r.end_date || '');

                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(institution) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(partnerType) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(country) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(agreementType) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(parsedMeta.field || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(signDate) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(endDate) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(parsedMeta.sdgs || '—') + '</td>' +
                    '<td class="py-3 px-4 text-right">' +
                    '<span class="linkage-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0" aria-label="Row actions">more_vert</span>' +
                    '</td>';

                linkagesTbody.appendChild(tr);
            });
        }

        async function loadLinkages() {
            if (!linkagesTbody) return;
            try {
                linkagesTbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            } catch (_) {}

            try {
                var resp = await fetch('api/mou-moa.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) {
                    throw new Error((result && result.error) ? result.error : 'Failed to load linkages');
                }
                renderLinkages(result.data || []);
            } catch (_) {
                renderLinkages([]);
            }
        }

        var linkagesCloseBtn = document.getElementById('linkages-entry-modal-close');
        if (linkagesCloseBtn) linkagesCloseBtn.addEventListener('click', closeLinkagesModal);
        var linkagesBackdropEl = document.getElementById('linkages-entry-modal-backdrop');
        if (linkagesBackdropEl) linkagesBackdropEl.addEventListener('click', closeLinkagesModal);
        var lpDiscardBtn = document.getElementById('lp-btn-discard');
        if (lpDiscardBtn) lpDiscardBtn.addEventListener('click', closeLinkagesModal);
        var lpSaveDraftBtn = document.getElementById('lp-btn-save-draft');
        if (lpSaveDraftBtn) {
            lpSaveDraftBtn.addEventListener('click', function () {
                // Draft = save without end date so backend marks as Pending.
                var endDateEl = document.getElementById('lp-end-date');
                if (endDateEl) endDateEl.dataset.__draftOriginal = endDateEl.value || '';
                // Save draft payload (no UI update table exists here yet).
                saveLinkagesToDatabase({ isDraft: true }).finally(function () {
                    closeLinkagesModal();
                    // Restore the user input (best effort) so they don't lose what they typed.
                    if (endDateEl && typeof endDateEl.dataset.__draftOriginal !== 'undefined') {
                        endDateEl.value = endDateEl.dataset.__draftOriginal;
                        delete endDateEl.dataset.__draftOriginal;
                    }
                });
            });
        }
        var lpCreateEntryBtn = document.getElementById('lp-btn-create-entry');
        if (lpCreateEntryBtn) {
            lpCreateEntryBtn.addEventListener('click', function () {
                saveLinkagesToDatabase({ isDraft: false }).then(function () {
                    closeLinkagesModal();
                });
            });
        }

        // Linkages row actions (kebab menu -> Edit/Delete)
        if (linkagesTbody) {
            var activeLinkageRow = null;
            var linkagesMenu = document.getElementById('linkages-row-actions-menu');
            if (!linkagesMenu) {
                linkagesMenu = document.createElement('div');
                linkagesMenu.id = 'linkages-row-actions-menu';
                linkagesMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                linkagesMenu.setAttribute('role', 'menu');
                linkagesMenu.innerHTML =
                    '<button type="button" id="linkages-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Edit</button>' +
                    '<button type="button" id="linkages-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Delete</button>';
                document.body.appendChild(linkagesMenu);
            }

            var closeLinkagesMenu = function () {
                if (linkagesMenu) linkagesMenu.classList.add('hidden');
                activeLinkageRow = null;
            };

            var openLinkagesMenuForBtn = function (btn) {
                activeLinkageRow = btn ? btn.closest('tr') : null;
                if (!activeLinkageRow) return;
                var rect = btn.getBoundingClientRect();
                linkagesMenu.style.left = Math.max(12, rect.left) + 'px';
                linkagesMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                linkagesMenu.classList.remove('hidden');
            };

            var setSelectValue = function (selectEl, value) {
                if (!selectEl) return;
                var desired = (value || '').trim();
                var hasOption = Array.from(selectEl.options || []).some(function (opt) {
                    return String(opt.value || '').trim() === desired;
                });
                if (!hasOption && desired) {
                    var opt = document.createElement('option');
                    opt.value = desired;
                    opt.textContent = desired;
                    selectEl.appendChild(opt);
                }
                selectEl.value = desired;
            };

            linkagesTbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.linkage-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openLinkagesMenuForBtn(btn);
            });

            linkagesTbody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.linkage-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    openLinkagesMenuForBtn(btn);
                }
            });

            var linkageEditBtn = document.getElementById('linkages-row-edit-btn');
            if (linkageEditBtn) {
                linkageEditBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!activeLinkageRow) return;

                    var idVal = activeLinkageRow.getAttribute('data-linkage-id') || '';
                    var institutionVal = activeLinkageRow.getAttribute('data-linkage-institution') || '';
                    var partnerVal = activeLinkageRow.getAttribute('data-linkage-partner') || '';
                    var countryVal = activeLinkageRow.getAttribute('data-linkage-country') || '';
                    var agreementVal = activeLinkageRow.getAttribute('data-linkage-agreement') || '';
                    var fieldVal = activeLinkageRow.getAttribute('data-linkage-field') || '';
                    var sdgsVal = activeLinkageRow.getAttribute('data-linkage-sdgs') || '';
                    var signDateVal = activeLinkageRow.getAttribute('data-linkage-sign-date') || '';
                    var endDateVal = activeLinkageRow.getAttribute('data-linkage-end-date') || '';

                    openLinkagesModal();
                    editingLinkageId = idVal ? parseInt(idVal, 10) : null;
                    editingLinkageSdgs = sdgsVal;

                    var titleEl = document.getElementById('linkages-entry-modal-title');
                    if (titleEl) titleEl.textContent = 'Edit Linkages & Partnerships';
                    var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                    if (subtitleEl) subtitleEl.textContent = 'Update the record details, then save changes.';
                    var createBtn = document.getElementById('lp-btn-create-entry');
                    if (createBtn) createBtn.textContent = 'Save Changes';

                    var institutionEl = document.getElementById('lp-partner-institution');
                    var partnerEl = document.getElementById('lp-type-of-partner');
                    var countryEl = document.getElementById('lp-country');
                    var agreementEl = document.getElementById('lp-type-of-agreement');
                    var fieldEl = document.getElementById('lp-field');
                    var signDateEl = document.getElementById('lp-date-signed');
                    var endDateEl = document.getElementById('lp-end-date');

                    if (institutionEl) institutionEl.value = institutionVal;
                    if (fieldEl) fieldEl.value = fieldVal;
                    if (signDateEl) signDateEl.value = signDateVal;
                    if (endDateEl) endDateEl.value = endDateVal;
                    setSelectValue(partnerEl, partnerVal);
                    setSelectValue(countryEl, countryVal);
                    setSelectValue(agreementEl, agreementVal);

                    closeLinkagesMenu();
                });
            }

            var linkageDeleteBtn = document.getElementById('linkages-row-delete-btn');
            if (linkageDeleteBtn) {
                linkageDeleteBtn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    if (!activeLinkageRow) return;
                    var rowId = activeLinkageRow.getAttribute('data-linkage-id');
                    if (!rowId) return;
                    var institution = activeLinkageRow.getAttribute('data-linkage-institution') || 'this record';
                    var ok = window.confirm('Delete linkage record for "' + institution + '"?');
                    if (!ok) return;
                    try {
                        var resp = await fetch('api/mou-moa.php?id=' + encodeURIComponent(rowId), { method: 'DELETE', credentials: 'same-origin' });
                        var result = await resp.json();
                        if (!resp.ok || !result || !result.success) {
                            throw new Error((result && result.error) ? result.error : 'Delete failed');
                        }
                        await loadLinkages();
                    } catch (_) {
                        window.alert('Failed to delete linkage from the server.');
                    }
                    closeLinkagesMenu();
                });
            }

            document.addEventListener('click', function () {
                closeLinkagesMenu();
            });
            window.addEventListener('scroll', function () {
                closeLinkagesMenu();
            }, true);
        }

        var studentMobilityTbody = document.getElementById('student-mobility-table-body');

        function formatStudentMobilityYear(value) {
            if (value === null || value === undefined || String(value).trim() === '') return '—';
            return String(value);
        }

        function formatStudentScope(value) {
            var raw = String(value || '').trim().toLowerCase();
            return raw === 'outbound' ? 'Outbound' : 'Inbound';
        }

        function formatStudentDuration(value) {
            var raw = String(value || '').trim().toLowerCase();
            return raw === 'long' ? 'Long Term' : 'Short Term';
        }

        async function saveStudentMobilityToDatabase() {
            var partnerEl = document.getElementById('student-partner-institution');
            var countryEl = document.getElementById('student-country');
            var modalityEl = document.getElementById('student-activity-modality');
            var durationEl = document.getElementById('student-duration-value');
            var scopeEl = document.getElementById('student-scope-value');
            var programEl = document.getElementById('student-program-name');
            var startYearEl = document.getElementById('student-start-year');
            var endYearEl = document.getElementById('student-end-year');

            var partnerInstitution = partnerEl ? (partnerEl.value || '').trim() : '';
            var country = countryEl ? (countryEl.value || '').trim() : '';
            var activityModality = modalityEl ? (modalityEl.value || '').trim() : '';
            var durationType = durationEl ? (durationEl.value || '').trim() : 'short';
            var scope = scopeEl ? (scopeEl.value || '').trim() : 'inbound';
            var programName = programEl ? (programEl.value || '').trim() : '';
            var startYearRaw = startYearEl ? (startYearEl.value || '').trim() : '';
            var endYearRaw = endYearEl ? (endYearEl.value || '').trim() : '';

            var toast = function (message, isError) {
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                    (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () {
                    if (el && el.parentNode) el.parentNode.removeChild(el);
                }, 3000);
            };

            var startYear = startYearRaw === '' ? null : parseInt(startYearRaw, 10);
            var endYear = endYearRaw === '' ? null : parseInt(endYearRaw, 10);

            if (!partnerInstitution || !country || !programName) {
                toast('Please complete Partner Institution, Country, and Program Name.', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && (isNaN(startYear) || startYear < 1900 || startYear > 2100)) {
                toast('Please provide a valid Starting Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (endYear !== null && (isNaN(endYear) || endYear < 1900 || endYear > 2100)) {
                toast('Please provide a valid End Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && endYear !== null && endYear < startYear) {
                toast('End Year must be greater than or equal to Starting Year.', true);
                throw new Error('Validation failed');
            }

            var formData = new FormData();
            formData.append('partner_institution', partnerInstitution);
            formData.append('country', country);
            formData.append('scope', scope);
            formData.append('activity_modality', activityModality);
            formData.append('duration_type', durationType);
            formData.append('program_name', programName);
            formData.append('start_year', startYear === null ? '' : String(startYear));
            formData.append('end_year', endYear === null ? '' : String(endYear));

            var endpoint = editingStudentMobilityId
                ? ('api/student-mobility.php?action=update&id=' + encodeURIComponent(String(editingStudentMobilityId)))
                : 'api/student-mobility.php';
            var response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await response.json();
            if (!response.ok || !result || !result.success) {
                var err = (result && result.error) ? result.error : 'Failed to save student mobility record';
                toast(err, true);
                throw new Error(err);
            }

            if (typeof loadStudentMobility === 'function') {
                await loadStudentMobility();
            }
            toast(result.message || 'Student mobility record saved.', false);
            return result;
        }

        function renderStudentMobility(entries) {
            if (!studentMobilityTbody) return;
            studentMobilityTbody.innerHTML = '';
            var rows = entries || [];
            if (rows.length === 0) {
                var emptyTr = document.createElement('tr');
                emptyTr.innerHTML = '<td colspan="9" class="py-10 text-center text-on-surface-variant text-sm">No student mobility records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                studentMobilityTbody.appendChild(emptyTr);
                return;
            }

            rows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-student-mobility-id', String(r.id || ''));
                tr.setAttribute('data-student-mobility-partner', r.partner_institution || '');
                tr.setAttribute('data-student-mobility-country', r.country || '');
                tr.setAttribute('data-student-mobility-scope', r.scope || 'inbound');
                tr.setAttribute('data-student-mobility-modality', r.activity_modality || '');
                tr.setAttribute('data-student-mobility-duration', r.duration_type || 'short');
                tr.setAttribute('data-student-mobility-program', r.program_name || '');
                tr.setAttribute('data-student-mobility-start-year', r.start_year || '');
                tr.setAttribute('data-student-mobility-end-year', r.end_year || '');

                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.partner_institution || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.country || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentScope(r.scope)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.activity_modality || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentDuration(r.duration_type)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.program_name || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.start_year)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.end_year)) + '</td>' +
                    '<td class="py-3 px-4 text-right">' +
                        '<span class="student-mobility-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0" aria-label="Row actions">more_vert</span>' +
                    '</td>';
                studentMobilityTbody.appendChild(tr);
            });
        }

        async function loadStudentMobility() {
            if (!studentMobilityTbody) return;
            try {
                studentMobilityTbody.innerHTML = '<tr><td colspan="9" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            } catch (_) {}
            try {
                var resp = await fetch('api/student-mobility.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) {
                    throw new Error((result && result.error) ? result.error : 'Failed to load student mobility records');
                }
                renderStudentMobility(result.data || []);
            } catch (_) {
                renderStudentMobility([]);
            }
        }

        if (studentMobilityTbody) {
            var activeStudentMobilityRow = null;
            var studentMobilityMenu = document.getElementById('student-mobility-row-actions-menu');
            if (!studentMobilityMenu) {
                studentMobilityMenu = document.createElement('div');
                studentMobilityMenu.id = 'student-mobility-row-actions-menu';
                studentMobilityMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                studentMobilityMenu.setAttribute('role', 'menu');
                studentMobilityMenu.innerHTML =
                    '<button type="button" id="student-mobility-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Edit</button>' +
                    '<button type="button" id="student-mobility-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Delete</button>';
                document.body.appendChild(studentMobilityMenu);
            }

            var closeStudentMobilityMenu = function () {
                if (studentMobilityMenu) studentMobilityMenu.classList.add('hidden');
                activeStudentMobilityRow = null;
            };

            var openStudentMobilityMenuForBtn = function (btn) {
                activeStudentMobilityRow = btn ? btn.closest('tr') : null;
                if (!activeStudentMobilityRow) return;
                var rect = btn.getBoundingClientRect();
                studentMobilityMenu.style.left = Math.max(12, rect.left) + 'px';
                studentMobilityMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                studentMobilityMenu.classList.remove('hidden');
            };

            var setSelectValue = function (selectEl, value) {
                if (!selectEl) return;
                var desired = (value || '').trim();
                var hasOption = Array.from(selectEl.options || []).some(function (opt) {
                    return String(opt.value || '').trim() === desired;
                });
                if (!hasOption && desired) {
                    var opt = document.createElement('option');
                    opt.value = desired;
                    opt.textContent = desired;
                    selectEl.appendChild(opt);
                }
                selectEl.value = desired;
            };

            studentMobilityTbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.student-mobility-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openStudentMobilityMenuForBtn(btn);
            });

            studentMobilityTbody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.student-mobility-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    openStudentMobilityMenuForBtn(btn);
                }
            });

            var studentMobilityEditBtn = document.getElementById('student-mobility-row-edit-btn');
            if (studentMobilityEditBtn) {
                studentMobilityEditBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!activeStudentMobilityRow) return;

                    var idVal = activeStudentMobilityRow.getAttribute('data-student-mobility-id') || '';
                    var partnerVal = activeStudentMobilityRow.getAttribute('data-student-mobility-partner') || '';
                    var countryVal = activeStudentMobilityRow.getAttribute('data-student-mobility-country') || '';
                    var scopeVal = activeStudentMobilityRow.getAttribute('data-student-mobility-scope') || 'inbound';
                    var modalityVal = activeStudentMobilityRow.getAttribute('data-student-mobility-modality') || '';
                    var durationVal = activeStudentMobilityRow.getAttribute('data-student-mobility-duration') || 'short';
                    var programVal = activeStudentMobilityRow.getAttribute('data-student-mobility-program') || '';
                    var startYearVal = activeStudentMobilityRow.getAttribute('data-student-mobility-start-year') || '';
                    var endYearVal = activeStudentMobilityRow.getAttribute('data-student-mobility-end-year') || '';

                    editingStudentMobilityId = idVal ? parseInt(idVal, 10) : null;
                    openStudentMobilityModal();

                    var partnerEl = document.getElementById('student-partner-institution');
                    var countryEl = document.getElementById('student-country');
                    var modalityEl = document.getElementById('student-activity-modality');
                    var programEl = document.getElementById('student-program-name');
                    var startYearEl = document.getElementById('student-start-year');
                    var endYearEl = document.getElementById('student-end-year');
                    var scopeValueEl = document.getElementById('student-scope-value');
                    var durationValueEl = document.getElementById('student-duration-value');

                    if (partnerEl) partnerEl.value = partnerVal;
                    setSelectValue(countryEl, countryVal);
                    setSelectValue(modalityEl, modalityVal);
                    if (programEl) programEl.value = programVal;
                    if (startYearEl) startYearEl.value = startYearVal;
                    if (endYearEl) endYearEl.value = endYearVal;
                    if (scopeValueEl) scopeValueEl.value = scopeVal;
                    if (durationValueEl) durationValueEl.value = durationVal;
                    updateStudentScopeUI(String(scopeVal).toLowerCase() !== 'outbound');
                    updateStudentDurationUI(String(durationVal).toLowerCase() === 'long' ? 'long' : 'short');

                    var titleEl = document.getElementById('student-mobility-entry-modal-title');
                    if (titleEl) titleEl.textContent = 'Edit Student Mobility & Internship Record';
                    var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                    if (subtitleEl) subtitleEl.textContent = 'Update the record details, then save changes.';
                    var saveBtn = document.getElementById('student-mobility-entry-save');
                    if (saveBtn) saveBtn.textContent = 'Save Changes';

                    closeStudentMobilityMenu();
                });
            }

            var studentMobilityDeleteBtn = document.getElementById('student-mobility-row-delete-btn');
            if (studentMobilityDeleteBtn) {
                studentMobilityDeleteBtn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    if (!activeStudentMobilityRow) return;
                    var recordId = activeStudentMobilityRow.getAttribute('data-student-mobility-id');
                    if (!recordId) return;
                    var programText = activeStudentMobilityRow.getAttribute('data-student-mobility-program') || 'this record';
                    var ok = window.confirm('Delete student mobility record for "' + programText + '"?');
                    if (!ok) return;
                    try {
                        var resp = await fetch('api/student-mobility.php?id=' + encodeURIComponent(recordId), { method: 'DELETE', credentials: 'same-origin' });
                        var result = await resp.json();
                        if (!resp.ok || !result || !result.success) {
                            throw new Error((result && result.error) ? result.error : 'Delete failed');
                        }
                        await loadStudentMobility();
                    } catch (_) {
                        window.alert('Failed to delete student mobility record from the server.');
                    }
                    closeStudentMobilityMenu();
                });
            }

            document.addEventListener('click', function () {
                closeStudentMobilityMenu();
            });
            window.addEventListener('scroll', function () {
                closeStudentMobilityMenu();
            }, true);
        }

        var studentCloseBtn = document.getElementById('student-mobility-entry-modal-close');
        if (studentCloseBtn) studentCloseBtn.addEventListener('click', closeStudentMobilityModal);
        var studentBackdropEl = document.getElementById('student-mobility-entry-modal-backdrop');
        if (studentBackdropEl) studentBackdropEl.addEventListener('click', closeStudentMobilityModal);
        var studentCancelBtn = document.getElementById('student-mobility-entry-cancel');
        if (studentCancelBtn) studentCancelBtn.addEventListener('click', closeStudentMobilityModal);

        var studentForm = document.getElementById('student-mobility-entry-form');
        if (studentForm) {
            studentForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveStudentMobilityToDatabase().then(function () {
                    closeStudentMobilityModal();
                });
            });
        }
        var studentSaveBtn = document.getElementById('student-mobility-entry-save');
        if (studentSaveBtn) {
            studentSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveStudentMobilityToDatabase().then(function () {
                    closeStudentMobilityModal();
                });
            });
        }

        var scholarshipsTbody = document.getElementById('scholarships-table-body');

        function formatScholarshipYear(value) {
            if (value === null || value === undefined || String(value).trim() === '') return '—';
            return String(value);
        }

        async function saveScholarshipToDatabase() {
            var partnerEl = document.getElementById('scholarship-partner-institution');
            var countryEl = document.getElementById('scholarship-country');
            var scopeEl = document.getElementById('scholarship-scope-value');
            var programTypeEl = document.getElementById('scholarship-program-type');
            var fieldEl = document.getElementById('scholarship-field');
            var countEl = document.getElementById('scholarship-faculty-count');
            var modalityEl = document.getElementById('scholarship-modality-value');
            var startYearEl = document.getElementById('scholarship-start-year');
            var endYearEl = document.getElementById('scholarship-end-year');

            var partnerInstitution = partnerEl ? (partnerEl.value || '').trim() : '';
            var country = countryEl ? (countryEl.value || '').trim() : '';
            var scope = scopeEl ? (scopeEl.value || '').trim() : 'inbound';
            var programType = programTypeEl ? (programTypeEl.value || '').trim() : '';
            var field = fieldEl ? (fieldEl.value || '').trim() : '';
            var facultyCountRaw = countEl ? (countEl.value || '').trim() : '';
            var modality = modalityEl ? (modalityEl.value || '').trim() : 'on-site';
            var startYearRaw = startYearEl ? (startYearEl.value || '').trim() : '';
            var endYearRaw = endYearEl ? (endYearEl.value || '').trim() : '';

            var toast = function (message, isError) {
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                    (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () {
                    if (el && el.parentNode) el.parentNode.removeChild(el);
                }, 3000);
            };

            var facultyCount = facultyCountRaw === '' ? 0 : parseInt(facultyCountRaw, 10);
            var startYear = startYearRaw === '' ? null : parseInt(startYearRaw, 10);
            var endYear = endYearRaw === '' ? null : parseInt(endYearRaw, 10);

            if (!partnerInstitution || !country || !programType) {
                toast('Please complete Partner Institution, Country, and Program Type.', true);
                throw new Error('Validation failed');
            }
            if (isNaN(facultyCount) || facultyCount < 0) {
                toast('Faculty/Staff count must be 0 or greater.', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && (isNaN(startYear) || startYear < 1900 || startYear > 2100)) {
                toast('Please provide a valid Starting Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (endYear !== null && (isNaN(endYear) || endYear < 1900 || endYear > 2100)) {
                toast('Please provide a valid End Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && endYear !== null && endYear < startYear) {
                toast('End Year must be greater than or equal to Starting Year.', true);
                throw new Error('Validation failed');
            }

            var formData = new FormData();
            formData.append('partner_institution', partnerInstitution);
            formData.append('country', country);
            formData.append('scope', scope);
            formData.append('program_type', programType);
            formData.append('field_specialization', field);
            formData.append('faculty_staff_count', String(facultyCount));
            formData.append('modality', modality);
            formData.append('start_year', startYear === null ? '' : String(startYear));
            formData.append('end_year', endYear === null ? '' : String(endYear));

            var endpoint = editingScholarshipId
                ? ('api/international-scholarships.php?action=update&id=' + encodeURIComponent(String(editingScholarshipId)))
                : 'api/international-scholarships.php';
            var response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await response.json();
            if (!response.ok || !result || !result.success) {
                var err = (result && result.error) ? result.error : 'Failed to save scholarship record';
                toast(err, true);
                throw new Error(err);
            }
            if (typeof loadScholarships === 'function') await loadScholarships();
            toast(result.message || 'Scholarship record saved.', false);
            return result;
        }

        function renderScholarships(entries) {
            if (!scholarshipsTbody) return;
            scholarshipsTbody.innerHTML = '';
            var rows = entries || [];
            if (rows.length === 0) {
                var emptyTr = document.createElement('tr');
                emptyTr.innerHTML = '<td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">No scholarship records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                scholarshipsTbody.appendChild(emptyTr);
                return;
            }

            rows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-scholarship-id', String(r.id || ''));
                tr.setAttribute('data-scholarship-partner', r.partner_institution || '');
                tr.setAttribute('data-scholarship-country', r.country || '');
                tr.setAttribute('data-scholarship-scope', r.scope || 'inbound');
                tr.setAttribute('data-scholarship-program-type', r.program_type || '');
                tr.setAttribute('data-scholarship-field', r.field_specialization || '');
                tr.setAttribute('data-scholarship-count', r.faculty_staff_count || 0);
                tr.setAttribute('data-scholarship-modality', r.modality || 'on-site');
                tr.setAttribute('data-scholarship-start-year', r.start_year || '');
                tr.setAttribute('data-scholarship-end-year', r.end_year || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.partner_institution || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.country || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentScope(r.scope)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.program_type || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.field_specialization || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(String(r.faculty_staff_count === null || r.faculty_staff_count === undefined ? 0 : r.faculty_staff_count)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.modality || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatScholarshipYear(r.start_year)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatScholarshipYear(r.end_year)) + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="scholarship-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0" aria-label="Row actions">more_vert</span></td>';
                scholarshipsTbody.appendChild(tr);
            });
        }

        async function loadScholarships() {
            if (!scholarshipsTbody) return;
            scholarshipsTbody.innerHTML = '<tr><td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/international-scholarships.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderScholarships(result.data || []);
            } catch (_) {
                renderScholarships([]);
            }
        }

        if (scholarshipsTbody) {
            var activeScholarshipRow = null;
            var scholarshipMenu = document.getElementById('scholarship-row-actions-menu');
            if (!scholarshipMenu) {
                scholarshipMenu = document.createElement('div');
                scholarshipMenu.id = 'scholarship-row-actions-menu';
                scholarshipMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                scholarshipMenu.setAttribute('role', 'menu');
                scholarshipMenu.innerHTML =
                    '<button type="button" id="scholarship-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Edit</button>' +
                    '<button type="button" id="scholarship-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Delete</button>';
                document.body.appendChild(scholarshipMenu);
            }

            var closeScholarshipMenu = function () {
                if (scholarshipMenu) scholarshipMenu.classList.add('hidden');
                activeScholarshipRow = null;
            };
            var openScholarshipMenuForBtn = function (btn) {
                activeScholarshipRow = btn ? btn.closest('tr') : null;
                if (!activeScholarshipRow) return;
                var rect = btn.getBoundingClientRect();
                scholarshipMenu.style.left = Math.max(12, rect.left) + 'px';
                scholarshipMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                scholarshipMenu.classList.remove('hidden');
            };

            scholarshipsTbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.scholarship-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openScholarshipMenuForBtn(btn);
            });
            scholarshipsTbody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.scholarship-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openScholarshipMenuForBtn(btn);
                }
            });

            var scholarshipEditBtn = document.getElementById('scholarship-row-edit-btn');
            if (scholarshipEditBtn) {
                scholarshipEditBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!activeScholarshipRow) return;
                    editingScholarshipId = parseInt(activeScholarshipRow.getAttribute('data-scholarship-id') || '', 10) || null;
                    openScholarshipsModal();

                    var partnerEl = document.getElementById('scholarship-partner-institution');
                    var countryEl = document.getElementById('scholarship-country');
                    var scopeEl = document.getElementById('scholarship-scope-value');
                    var programTypeEl = document.getElementById('scholarship-program-type');
                    var fieldEl = document.getElementById('scholarship-field');
                    var countEl = document.getElementById('scholarship-faculty-count');
                    var modalityEl = document.getElementById('scholarship-modality-value');
                    var startYearEl = document.getElementById('scholarship-start-year');
                    var endYearEl = document.getElementById('scholarship-end-year');
                    if (partnerEl) partnerEl.value = activeScholarshipRow.getAttribute('data-scholarship-partner') || '';
                    if (countryEl) countryEl.value = activeScholarshipRow.getAttribute('data-scholarship-country') || '';
                    if (scopeEl) scopeEl.value = activeScholarshipRow.getAttribute('data-scholarship-scope') || 'inbound';
                    if (programTypeEl) programTypeEl.value = activeScholarshipRow.getAttribute('data-scholarship-program-type') || '';
                    if (fieldEl) fieldEl.value = activeScholarshipRow.getAttribute('data-scholarship-field') || '';
                    if (countEl) countEl.value = activeScholarshipRow.getAttribute('data-scholarship-count') || '0';
                    if (modalityEl) modalityEl.value = activeScholarshipRow.getAttribute('data-scholarship-modality') || 'on-site';
                    if (startYearEl) startYearEl.value = activeScholarshipRow.getAttribute('data-scholarship-start-year') || '';
                    if (endYearEl) endYearEl.value = activeScholarshipRow.getAttribute('data-scholarship-end-year') || '';
                    updateScholarshipScopeUI((scopeEl && scopeEl.value) !== 'outbound');
                    updateScholarshipModalityUI((modalityEl && modalityEl.value) || 'on-site');

                    var titleEl = document.getElementById('scholarships-entry-modal-title');
                    if (titleEl) titleEl.textContent = 'Edit International Scholarships and Fellowships';
                    var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                    if (subtitleEl) subtitleEl.textContent = 'Update the record details, then save changes.';
                    var saveBtn = document.getElementById('scholarships-entry-save');
                    if (saveBtn) saveBtn.textContent = 'Save Changes';
                    closeScholarshipMenu();
                });
            }

            var scholarshipDeleteBtn = document.getElementById('scholarship-row-delete-btn');
            if (scholarshipDeleteBtn) {
                scholarshipDeleteBtn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    if (!activeScholarshipRow) return;
                    var recordId = activeScholarshipRow.getAttribute('data-scholarship-id');
                    if (!recordId) return;
                    var programText = activeScholarshipRow.getAttribute('data-scholarship-program-type') || 'this record';
                    var ok = window.confirm('Delete scholarship record for "' + programText + '"?');
                    if (!ok) return;
                    try {
                        var resp = await fetch('api/international-scholarships.php?id=' + encodeURIComponent(recordId), { method: 'DELETE', credentials: 'same-origin' });
                        var result = await resp.json();
                        if (!resp.ok || !result || !result.success) throw new Error();
                        await loadScholarships();
                    } catch (_) {
                        window.alert('Failed to delete scholarship record from the server.');
                    }
                    closeScholarshipMenu();
                });
            }

            document.addEventListener('click', function () { closeScholarshipMenu(); });
            window.addEventListener('scroll', function () { closeScholarshipMenu(); }, true);
        }

        var scholarshipsCloseBtn = document.getElementById('scholarships-entry-modal-close');
        if (scholarshipsCloseBtn) scholarshipsCloseBtn.addEventListener('click', closeScholarshipsModal);
        var scholarshipsBackdropEl = document.getElementById('scholarships-entry-modal-backdrop');
        if (scholarshipsBackdropEl) scholarshipsBackdropEl.addEventListener('click', closeScholarshipsModal);
        var scholarshipsDiscardBtn = document.getElementById('scholarships-entry-discard');
        if (scholarshipsDiscardBtn) scholarshipsDiscardBtn.addEventListener('click', closeScholarshipsModal);
        var scholarshipsForm = document.getElementById('scholarships-entry-form');
        if (scholarshipsForm) {
            scholarshipsForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveScholarshipToDatabase().then(function () {
                    closeScholarshipsModal();
                });
            });
        }
        var scholarshipsSaveBtn = document.getElementById('scholarships-entry-save');
        if (scholarshipsSaveBtn) {
            scholarshipsSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveScholarshipToDatabase().then(function () {
                    closeScholarshipsModal();
                });
            });
        }

        var staffMobilityTbody = document.getElementById('staff-mobility-table-body');

        async function saveStaffMobilityToDatabase() {
            var partnerEl = document.getElementById('staff-partner-institution');
            var countryEl = document.getElementById('staff-country');
            var scopeEl = document.getElementById('staff-scope-value');
            var programTypeEl = document.getElementById('staff-program-type');
            var modalityEl = document.getElementById('staff-modality-value');
            var startYearEl = document.getElementById('staff-start-year');
            var endYearEl = document.getElementById('staff-end-year');

            var partnerInstitution = partnerEl ? (partnerEl.value || '').trim() : '';
            var country = countryEl ? (countryEl.value || '').trim() : '';
            var scope = scopeEl ? (scopeEl.value || '').trim() : 'inbound';
            var programType = programTypeEl ? (programTypeEl.value || '').trim() : '';
            var modality = modalityEl ? (modalityEl.value || '').trim() : 'physical';
            var startYearRaw = startYearEl ? (startYearEl.value || '').trim() : '';
            var endYearRaw = endYearEl ? (endYearEl.value || '').trim() : '';

            var toast = function (message, isError) {
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                    (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () {
                    if (el && el.parentNode) el.parentNode.removeChild(el);
                }, 3000);
            };

            var startYear = startYearRaw === '' ? null : parseInt(startYearRaw, 10);
            var endYear = endYearRaw === '' ? null : parseInt(endYearRaw, 10);

            if (!partnerInstitution || !country || !programType) {
                toast('Please complete Partner Institution, Country, and Program Type.', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && (isNaN(startYear) || startYear < 1900 || startYear > 2100)) {
                toast('Please provide a valid Starting Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (endYear !== null && (isNaN(endYear) || endYear < 1900 || endYear > 2100)) {
                toast('Please provide a valid End Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && endYear !== null && endYear < startYear) {
                toast('End Year must be greater than or equal to Starting Year.', true);
                throw new Error('Validation failed');
            }

            var formData = new FormData();
            formData.append('partner_institution', partnerInstitution);
            formData.append('country', country);
            formData.append('scope', scope);
            formData.append('program_type', programType);
            formData.append('modality', modality);
            formData.append('start_year', startYear === null ? '' : String(startYear));
            formData.append('end_year', endYear === null ? '' : String(endYear));

            var endpoint = editingStaffMobilityId
                ? ('api/staff-mobility.php?action=update&id=' + encodeURIComponent(String(editingStaffMobilityId)))
                : 'api/staff-mobility.php';
            var response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await response.json();
            if (!response.ok || !result || !result.success) {
                var err = (result && result.error) ? result.error : 'Failed to save staff mobility record';
                toast(err, true);
                throw new Error(err);
            }
            if (typeof loadStaffMobility === 'function') await loadStaffMobility();
            toast(result.message || 'Staff mobility record saved.', false);
            return result;
        }

        function renderStaffMobility(entries) {
            if (!staffMobilityTbody) return;
            staffMobilityTbody.innerHTML = '';
            var rows = entries || [];
            if (rows.length === 0) {
                var emptyTr = document.createElement('tr');
                emptyTr.innerHTML = '<td colspan="8" class="py-10 text-center text-on-surface-variant text-sm">No staff mobility records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                staffMobilityTbody.appendChild(emptyTr);
                return;
            }

            rows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-staff-id', String(r.id || ''));
                tr.setAttribute('data-staff-partner', r.partner_institution || '');
                tr.setAttribute('data-staff-country', r.country || '');
                tr.setAttribute('data-staff-scope', r.scope || 'inbound');
                tr.setAttribute('data-staff-program-type', r.program_type || '');
                tr.setAttribute('data-staff-modality', r.modality || 'physical');
                tr.setAttribute('data-staff-start-year', r.start_year || '');
                tr.setAttribute('data-staff-end-year', r.end_year || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.partner_institution || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.country || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentScope(r.scope)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.program_type || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.modality || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.start_year)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.end_year)) + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="staff-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0" aria-label="Row actions">more_vert</span></td>';
                staffMobilityTbody.appendChild(tr);
            });
        }

        async function loadStaffMobility() {
            if (!staffMobilityTbody) return;
            staffMobilityTbody.innerHTML = '<tr><td colspan="8" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/staff-mobility.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderStaffMobility(result.data || []);
            } catch (_) {
                renderStaffMobility([]);
            }
        }

        if (staffMobilityTbody) {
            var activeStaffRow = null;
            var staffMenu = document.getElementById('staff-row-actions-menu');
            if (!staffMenu) {
                staffMenu = document.createElement('div');
                staffMenu.id = 'staff-row-actions-menu';
                staffMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                staffMenu.setAttribute('role', 'menu');
                staffMenu.innerHTML =
                    '<button type="button" id="staff-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Edit</button>' +
                    '<button type="button" id="staff-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Delete</button>';
                document.body.appendChild(staffMenu);
            }

            var closeStaffMenu = function () {
                if (staffMenu) staffMenu.classList.add('hidden');
                activeStaffRow = null;
            };
            var openStaffMenuForBtn = function (btn) {
                activeStaffRow = btn ? btn.closest('tr') : null;
                if (!activeStaffRow) return;
                var rect = btn.getBoundingClientRect();
                staffMenu.style.left = Math.max(12, rect.left) + 'px';
                staffMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                staffMenu.classList.remove('hidden');
            };

            staffMobilityTbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.staff-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openStaffMenuForBtn(btn);
            });
            staffMobilityTbody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.staff-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openStaffMenuForBtn(btn);
                }
            });

            var staffEditBtn = document.getElementById('staff-row-edit-btn');
            if (staffEditBtn) {
                staffEditBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!activeStaffRow) return;
                    editingStaffMobilityId = parseInt(activeStaffRow.getAttribute('data-staff-id') || '', 10) || null;
                    openStaffMobilityModal();

                    var partnerEl = document.getElementById('staff-partner-institution');
                    var countryEl = document.getElementById('staff-country');
                    var scopeEl = document.getElementById('staff-scope-value');
                    var programTypeEl = document.getElementById('staff-program-type');
                    var modalityEl = document.getElementById('staff-modality-value');
                    var startYearEl = document.getElementById('staff-start-year');
                    var endYearEl = document.getElementById('staff-end-year');
                    if (partnerEl) partnerEl.value = activeStaffRow.getAttribute('data-staff-partner') || '';
                    if (countryEl) countryEl.value = activeStaffRow.getAttribute('data-staff-country') || '';
                    if (scopeEl) scopeEl.value = activeStaffRow.getAttribute('data-staff-scope') || 'inbound';
                    if (programTypeEl) programTypeEl.value = activeStaffRow.getAttribute('data-staff-program-type') || '';
                    if (modalityEl) modalityEl.value = activeStaffRow.getAttribute('data-staff-modality') || 'physical';
                    if (startYearEl) startYearEl.value = activeStaffRow.getAttribute('data-staff-start-year') || '';
                    if (endYearEl) endYearEl.value = activeStaffRow.getAttribute('data-staff-end-year') || '';
                    updateStaffScopeUI((scopeEl && scopeEl.value) !== 'outbound');
                    updateStaffModalityUI((modalityEl && modalityEl.value) || 'physical');

                    var titleEl = document.getElementById('staff-mobility-entry-modal-title');
                    if (titleEl) titleEl.textContent = 'Edit Staff Mobility and Scholarships';
                    var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                    if (subtitleEl) subtitleEl.textContent = 'Update the record details, then save changes.';
                    var saveBtn = document.getElementById('staff-mobility-entry-save');
                    if (saveBtn) saveBtn.textContent = 'Save Changes';
                    closeStaffMenu();
                });
            }

            var staffDeleteBtn = document.getElementById('staff-row-delete-btn');
            if (staffDeleteBtn) {
                staffDeleteBtn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    if (!activeStaffRow) return;
                    var recordId = activeStaffRow.getAttribute('data-staff-id');
                    if (!recordId) return;
                    var label = activeStaffRow.getAttribute('data-staff-program-type') || 'this record';
                    var ok = window.confirm('Delete staff mobility record for "' + label + '"?');
                    if (!ok) return;
                    try {
                        var resp = await fetch('api/staff-mobility.php?id=' + encodeURIComponent(recordId), { method: 'DELETE', credentials: 'same-origin' });
                        var result = await resp.json();
                        if (!resp.ok || !result || !result.success) throw new Error();
                        await loadStaffMobility();
                    } catch (_) {
                        window.alert('Failed to delete staff mobility record from the server.');
                    }
                    closeStaffMenu();
                });
            }

            document.addEventListener('click', function () { closeStaffMenu(); });
            window.addEventListener('scroll', function () { closeStaffMenu(); }, true);
        }

        var staffCloseBtn = document.getElementById('staff-mobility-entry-modal-close');
        if (staffCloseBtn) staffCloseBtn.addEventListener('click', closeStaffMobilityModal);
        var staffBackdropEl = document.getElementById('staff-mobility-entry-modal-backdrop');
        if (staffBackdropEl) staffBackdropEl.addEventListener('click', closeStaffMobilityModal);
        var staffCancelBtn = document.getElementById('staff-mobility-entry-cancel');
        if (staffCancelBtn) staffCancelBtn.addEventListener('click', closeStaffMobilityModal);
        var staffForm = document.getElementById('staff-mobility-entry-form');
        if (staffForm) {
            staffForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveStaffMobilityToDatabase().then(function () {
                    closeStaffMobilityModal();
                });
            });
        }
        var staffSaveBtn = document.getElementById('staff-mobility-entry-save');
        if (staffSaveBtn) {
            staffSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveStaffMobilityToDatabase().then(function () {
                    closeStaffMobilityModal();
                });
            });
        }

        var fullTimeStudentsTbody = document.getElementById('full-time-students-table-body');

        async function saveFullTimeStudentToDatabase() {
            var countryEl = document.getElementById('full-time-country');
            var countEl = document.getElementById('full-time-count');
            var programsEl = document.getElementById('full-time-programs');
            var modalityEl = document.getElementById('full-time-modality-value');
            var levelEl = document.getElementById('full-time-level-value');
            var startYearEl = document.getElementById('full-time-start-year');
            var endYearEl = document.getElementById('full-time-end-year');

            var country = countryEl ? (countryEl.value || '').trim() : '';
            var countRaw = countEl ? (countEl.value || '').trim() : '';
            var programs = programsEl ? (programsEl.value || '').trim() : '';
            var modality = modalityEl ? (modalityEl.value || '').trim() : 'on-site';
            var level = levelEl ? (levelEl.value || '').trim() : 'undergraduate';
            var startYearRaw = startYearEl ? (startYearEl.value || '').trim() : '';
            var endYearRaw = endYearEl ? (endYearEl.value || '').trim() : '';

            var toast = function (message, isError) {
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                    (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () { if (el && el.parentNode) el.parentNode.removeChild(el); }, 3000);
            };

            var count = parseInt(countRaw, 10);
            var startYear = startYearRaw === '' ? null : parseInt(startYearRaw, 10);
            var endYear = endYearRaw === '' ? null : parseInt(endYearRaw, 10);

            if (!country || !programs) {
                toast('Please complete Country of Origin and Academic Programs.', true);
                throw new Error('Validation failed');
            }
            if (isNaN(count) || count < 1) {
                toast('Number of students must be at least 1.', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && (isNaN(startYear) || startYear < 1900 || startYear > 2100)) {
                toast('Please provide a valid Starting Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (endYear !== null && (isNaN(endYear) || endYear < 1900 || endYear > 2100)) {
                toast('Please provide a valid End Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && endYear !== null && endYear < startYear) {
                toast('Estimated End Year must be greater than or equal to Starting Year.', true);
                throw new Error('Validation failed');
            }

            var formData = new FormData();
            formData.append('country_origin', country);
            formData.append('students_count', String(count));
            formData.append('programs', programs);
            formData.append('modality', modality);
            formData.append('study_level', level);
            formData.append('start_year', startYear === null ? '' : String(startYear));
            formData.append('end_year', endYear === null ? '' : String(endYear));

            var endpoint = editingFullTimeStudentId
                ? ('api/full-time-foreign-students.php?action=update&id=' + encodeURIComponent(String(editingFullTimeStudentId)))
                : 'api/full-time-foreign-students.php';
            var response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await response.json();
            if (!response.ok || !result || !result.success) {
                var err = (result && result.error) ? result.error : 'Failed to save full-time foreign student record';
                toast(err, true);
                throw new Error(err);
            }
            if (typeof loadFullTimeStudents === 'function') await loadFullTimeStudents();
            toast(result.message || 'Record saved successfully.', false);
            return result;
        }

        var fullTimeStudentsCache = [];
        var fullTimeStudentsDetailLevel = '';

        function normalizeFullTimeStudentsLevel(level) {
            return String(level || '').toLowerCase() === 'graduate' ? 'graduate' : 'undergraduate';
        }

        function formatFullTimeStudentsLevelLabel(level) {
            return normalizeFullTimeStudentsLevel(level) === 'graduate' ? 'Graduate' : 'Undergraduate';
        }

        function getFullTimeStudentsRowsByLevel(level) {
            var normalized = normalizeFullTimeStudentsLevel(level);
            return (fullTimeStudentsCache || []).filter(function (r) {
                return normalizeFullTimeStudentsLevel(r.study_level) === normalized;
            });
        }

        function openFullTimeStudentsDetailModal(level) {
            var modal = document.getElementById('full-time-students-detail-modal');
            if (!modal) return;
            fullTimeStudentsDetailLevel = normalizeFullTimeStudentsLevel(level);
            var titleEl = document.getElementById('full-time-students-detail-title');
            if (titleEl) titleEl.textContent = 'Full-Time Foreign Students - ' + formatFullTimeStudentsLevelLabel(fullTimeStudentsDetailLevel);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            renderFullTimeStudentsDetailRows();
        }

        function closeFullTimeStudentsDetailModal() {
            var modal = document.getElementById('full-time-students-detail-modal');
            if (!modal) return;
            fullTimeStudentsDetailLevel = '';
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function prefillFullTimeStudentFormFromRecord(record) {
            editingFullTimeStudentId = parseInt(record && record.id ? record.id : '0', 10) || null;
            openFullTimeForeignStudentsModal();
            var countryEl = document.getElementById('full-time-country');
            var countEl = document.getElementById('full-time-count');
            var programsEl = document.getElementById('full-time-programs');
            var modalityEl = document.getElementById('full-time-modality-value');
            var levelEl = document.getElementById('full-time-level-value');
            var startYearEl = document.getElementById('full-time-start-year');
            var endYearEl = document.getElementById('full-time-end-year');
            if (countryEl) countryEl.value = record && record.country_origin ? record.country_origin : '';
            if (countEl) countEl.value = record && record.students_count ? String(record.students_count) : '1';
            if (programsEl) programsEl.value = record && record.programs ? record.programs : '';
            if (modalityEl) modalityEl.value = record && record.modality ? record.modality : 'on-site';
            if (levelEl) levelEl.value = record && record.study_level ? record.study_level : 'undergraduate';
            if (startYearEl) startYearEl.value = record && record.start_year ? String(record.start_year) : '';
            if (endYearEl) endYearEl.value = record && record.end_year ? String(record.end_year) : '';
            updateFullTimeModalityUI((modalityEl && modalityEl.value) || 'on-site');
            updateFullTimeLevelUI((levelEl && levelEl.value) || 'undergraduate');
            var titleEl = document.getElementById('full-time-foreign-students-title');
            if (titleEl) titleEl.textContent = 'Edit Full-Time Foreign Students';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Update the record details, then save changes.';
            var saveBtn = document.getElementById('full-time-foreign-students-save');
            if (saveBtn) saveBtn.textContent = 'Save Changes';
        }

        function renderFullTimeStudentsDetailRows() {
            var detailBody = document.getElementById('full-time-students-detail-body');
            if (!detailBody) return;
            var levelRows = getFullTimeStudentsRowsByLevel(fullTimeStudentsDetailLevel);
            if (!levelRows.length) {
                detailBody.innerHTML = '<tr><td colspan="7" class="py-4 px-2 text-sm text-on-surface-variant">No records in this section.</td></tr>';
                return;
            }
            detailBody.innerHTML = '';
            levelRows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'border-b border-outline-variant/10 last:border-b-0';
                tr.innerHTML =
                    '<td class="py-3 px-2 text-sm font-semibold text-on-surface">' + escapeHtml(r.country_origin || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(String(r.students_count || 0)) + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(r.programs || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(r.modality || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(formatStudentMobilityYear(r.start_year)) + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(formatStudentMobilityYear(r.end_year)) + '</td>' +
                    '<td class="py-3 px-2 text-sm">' +
                        '<div class="flex items-center gap-2">' +
                            '<button type="button" class="full-time-student-detail-edit px-2 py-1 rounded-md bg-surface-container text-on-surface text-xs font-semibold hover:bg-surface-container-high" data-id="' + escapeHtml(String(r.id || '')) + '">Edit</button>' +
                            '<button type="button" class="full-time-student-detail-delete px-2 py-1 rounded-md bg-error-container text-on-error-container text-xs font-semibold hover:opacity-90" data-id="' + escapeHtml(String(r.id || '')) + '" data-country="' + escapeHtml(r.country_origin || '') + '">Delete</button>' +
                        '</div>' +
                    '</td>';
                detailBody.appendChild(tr);
            });
        }

        function renderFullTimeStudents(entries) {
            if (!fullTimeStudentsTbody) return;
            fullTimeStudentsTbody.innerHTML = '';
            fullTimeStudentsCache = Array.isArray(entries) ? entries : [];
            if (fullTimeStudentsCache.length === 0) {
                var emptyTr = document.createElement('tr');
                emptyTr.innerHTML = '<td colspan="3" class="py-10 text-center text-on-surface-variant text-sm">No foreign student records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                fullTimeStudentsTbody.appendChild(emptyTr);
                return;
            }

            ['undergraduate', 'graduate'].forEach(function (level) {
                var levelRows = getFullTimeStudentsRowsByLevel(level);
                var totalStudents = levelRows.reduce(function (sum, r) {
                    var n = parseInt(String(r.students_count || 0), 10);
                    return sum + (isNaN(n) ? 0 : n);
                }, 0);
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors cursor-pointer';
                tr.setAttribute('data-full-time-summary-level', level);
                tr.setAttribute('tabindex', '0');
                tr.setAttribute('role', 'button');
                tr.setAttribute('aria-label', 'Open ' + formatFullTimeStudentsLevelLabel(level) + ' records');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-semibold text-sm">' + escapeHtml(formatFullTimeStudentsLevelLabel(level)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-sm text-center">' + escapeHtml(String(totalStudents)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-sm text-center">' + escapeHtml(String(levelRows.length)) + '</td>';
                fullTimeStudentsTbody.appendChild(tr);
            });
        }

        async function loadFullTimeStudents() {
            if (!fullTimeStudentsTbody) return;
            fullTimeStudentsTbody.innerHTML = '<tr><td colspan="3" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/full-time-foreign-students.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderFullTimeStudents(result.data || []);
            } catch (_) {
                renderFullTimeStudents([]);
            }
        }

        if (fullTimeStudentsTbody) {
            fullTimeStudentsTbody.addEventListener('click', function (e) {
                var summaryRow = e.target.closest('tr[data-full-time-summary-level]');
                if (!summaryRow) return;
                var level = summaryRow.getAttribute('data-full-time-summary-level') || 'undergraduate';
                openFullTimeStudentsDetailModal(level);
            });
            fullTimeStudentsTbody.addEventListener('keydown', function (e) {
                var summaryRow = e.target.closest('tr[data-full-time-summary-level]');
                if (!summaryRow) return;
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                var level = summaryRow.getAttribute('data-full-time-summary-level') || 'undergraduate';
                openFullTimeStudentsDetailModal(level);
            });
        }

        var fullTimeStudentsDetailCloseBtn = document.getElementById('full-time-students-detail-close');
        if (fullTimeStudentsDetailCloseBtn) fullTimeStudentsDetailCloseBtn.addEventListener('click', closeFullTimeStudentsDetailModal);
        var fullTimeStudentsDetailBackdrop = document.getElementById('full-time-students-detail-backdrop');
        if (fullTimeStudentsDetailBackdrop) fullTimeStudentsDetailBackdrop.addEventListener('click', closeFullTimeStudentsDetailModal);
        var fullTimeStudentsDetailBody = document.getElementById('full-time-students-detail-body');
        if (fullTimeStudentsDetailBody) {
            fullTimeStudentsDetailBody.addEventListener('click', async function (e) {
                var editBtn = e.target.closest('.full-time-student-detail-edit');
                if (editBtn) {
                    var id = parseInt(editBtn.getAttribute('data-id') || '0', 10);
                    if (!id) return;
                    var record = (fullTimeStudentsCache || []).find(function (r) { return parseInt(String(r.id || '0'), 10) === id; });
                    if (!record) return;
                    closeFullTimeStudentsDetailModal();
                    prefillFullTimeStudentFormFromRecord(record);
                    return;
                }

                var delBtn = e.target.closest('.full-time-student-detail-delete');
                if (!delBtn) return;
                var recordId = delBtn.getAttribute('data-id') || '';
                if (!recordId) return;
                var label = delBtn.getAttribute('data-country') || 'this record';
                var ok = window.confirm('Delete full-time foreign student record for "' + label + '"?');
                if (!ok) return;
                try {
                    var resp = await fetch('api/full-time-foreign-students.php?id=' + encodeURIComponent(recordId), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadFullTimeStudents();
                    if (fullTimeStudentsDetailLevel) renderFullTimeStudentsDetailRows();
                } catch (_) {
                    window.alert('Failed to delete full-time foreign student record from the server.');
                }
            });
        }

        var fullTimeCloseBtn = document.getElementById('full-time-foreign-students-close');
        if (fullTimeCloseBtn) fullTimeCloseBtn.addEventListener('click', closeFullTimeForeignStudentsModal);
        var fullTimeBackdropEl = document.getElementById('full-time-foreign-students-backdrop');
        if (fullTimeBackdropEl) fullTimeBackdropEl.addEventListener('click', closeFullTimeForeignStudentsModal);
        var fullTimeCancelBtn = document.getElementById('full-time-foreign-students-cancel');
        if (fullTimeCancelBtn) fullTimeCancelBtn.addEventListener('click', closeFullTimeForeignStudentsModal);
        var fullTimeForm = document.getElementById('full-time-foreign-students-form');
        if (fullTimeForm) {
            fullTimeForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveFullTimeStudentToDatabase().then(function () {
                    closeFullTimeForeignStudentsModal();
                });
            });
        }
        var fullTimeSaveBtn = document.getElementById('full-time-foreign-students-save');
        if (fullTimeSaveBtn) {
            fullTimeSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveFullTimeStudentToDatabase().then(function () {
                    closeFullTimeForeignStudentsModal();
                });
            });
        }

        var fullTimeFacultyTbody = document.getElementById('full-time-faculty-table-body');

        async function saveFullTimeFacultyToDatabase() {
            var nameEl = document.getElementById('full-time-faculty-name');
            var citizenshipEl = document.getElementById('full-time-faculty-citizenship');
            var specializationEl = document.getElementById('full-time-faculty-specialization');
            var scopeEl = document.getElementById('full-time-faculty-scope-value');
            var levelEl = document.getElementById('full-time-faculty-level-value');
            var startYearEl = document.getElementById('full-time-faculty-start-year');
            var endYearEl = document.getElementById('full-time-faculty-end-year');

            var fullName = nameEl ? (nameEl.value || '').trim() : '';
            var citizenship = citizenshipEl ? (citizenshipEl.value || '').trim() : '';
            var specialization = specializationEl ? (specializationEl.value || '').trim() : '';
            var scope = scopeEl ? (scopeEl.value || '').trim() : 'inbound';
            var teachingLevel = levelEl ? (levelEl.value || '').trim() : 'undergraduate';
            var startYearRaw = startYearEl ? (startYearEl.value || '').trim() : '';
            var endYearRaw = endYearEl ? (endYearEl.value || '').trim() : '';

            var toast = function (message, isError) {
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                    (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () { if (el && el.parentNode) el.parentNode.removeChild(el); }, 3000);
            };

            var startYear = startYearRaw === '' ? null : parseInt(startYearRaw, 10);
            var endYear = endYearRaw === '' ? null : parseInt(endYearRaw, 10);

            if (!fullName || !citizenship || !specialization) {
                toast('Please complete Full Name, Citizenship, and Specialization.', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && (isNaN(startYear) || startYear < 1900 || startYear > 2100)) {
                toast('Please provide a valid Starting Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (endYear !== null && (isNaN(endYear) || endYear < 1900 || endYear > 2100)) {
                toast('Please provide a valid End Year (1900-2100).', true);
                throw new Error('Validation failed');
            }
            if (startYear !== null && endYear !== null && endYear < startYear) {
                toast('End Year must be greater than or equal to Starting Year.', true);
                throw new Error('Validation failed');
            }

            var formData = new FormData();
            formData.append('full_name', fullName);
            formData.append('citizenship', citizenship);
            formData.append('specialization', specialization);
            formData.append('scope', scope);
            formData.append('teaching_level', teachingLevel);
            formData.append('start_year', startYear === null ? '' : String(startYear));
            formData.append('end_year', endYear === null ? '' : String(endYear));

            var endpoint = editingFullTimeFacultyId
                ? ('api/full-time-foreign-faculty.php?action=update&id=' + encodeURIComponent(String(editingFullTimeFacultyId)))
                : 'api/full-time-foreign-faculty.php';
            var response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await response.json();
            if (!response.ok || !result || !result.success) {
                var err = (result && result.error) ? result.error : 'Failed to save full-time faculty record';
                toast(err, true);
                throw new Error(err);
            }
            if (typeof loadFullTimeFaculty === 'function') await loadFullTimeFaculty();
            toast(result.message || 'Record saved successfully.', false);
            return result;
        }

        var fullTimeFacultyCache = [];
        var fullTimeFacultyDetailLevel = '';

        function normalizeFullTimeFacultyLevel(level) {
            return String(level || '').toLowerCase() === 'graduate' ? 'graduate' : 'undergraduate';
        }

        function formatFullTimeFacultyLevelLabel(level) {
            return normalizeFullTimeFacultyLevel(level) === 'graduate' ? 'Teaching Graduate' : 'Teaching Undergraduate';
        }

        function getFullTimeFacultyRowsByLevel(level) {
            var normalized = normalizeFullTimeFacultyLevel(level);
            return (fullTimeFacultyCache || []).filter(function (r) {
                return normalizeFullTimeFacultyLevel(r.teaching_level) === normalized;
            });
        }

        function openFullTimeFacultyDetailModal(level) {
            var modal = document.getElementById('full-time-faculty-detail-modal');
            if (!modal) return;
            fullTimeFacultyDetailLevel = normalizeFullTimeFacultyLevel(level);
            var titleEl = document.getElementById('full-time-faculty-detail-title');
            if (titleEl) titleEl.textContent = 'Full-Time Foreign Faculty - ' + formatFullTimeFacultyLevelLabel(fullTimeFacultyDetailLevel);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            renderFullTimeFacultyDetailRows();
        }

        function closeFullTimeFacultyDetailModal() {
            var modal = document.getElementById('full-time-faculty-detail-modal');
            if (!modal) return;
            fullTimeFacultyDetailLevel = '';
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function prefillFullTimeFacultyFormFromRecord(record) {
            editingFullTimeFacultyId = parseInt(record && record.id ? record.id : '0', 10) || null;
            openFullTimeForeignFacultyModal();
            var nameEl = document.getElementById('full-time-faculty-name');
            var citizenshipEl = document.getElementById('full-time-faculty-citizenship');
            var specializationEl = document.getElementById('full-time-faculty-specialization');
            var scopeEl = document.getElementById('full-time-faculty-scope-value');
            var levelEl = document.getElementById('full-time-faculty-level-value');
            var startYearEl = document.getElementById('full-time-faculty-start-year');
            var endYearEl = document.getElementById('full-time-faculty-end-year');

            if (nameEl) nameEl.value = record && record.full_name ? record.full_name : '';
            if (citizenshipEl) {
                var citizenshipVal = record && record.citizenship ? record.citizenship : '';
                var hasOption = Array.prototype.some.call(citizenshipEl.options || [], function (opt) {
                    return (opt.value || '').toLowerCase() === citizenshipVal.toLowerCase();
                });
                if (!hasOption && citizenshipVal) {
                    var opt = document.createElement('option');
                    opt.value = citizenshipVal;
                    opt.textContent = citizenshipVal;
                    citizenshipEl.appendChild(opt);
                }
                citizenshipEl.value = citizenshipVal;
            }
            if (specializationEl) specializationEl.value = record && record.specialization ? record.specialization : '';
            if (scopeEl) scopeEl.value = record && record.scope ? record.scope : 'inbound';
            if (levelEl) levelEl.value = record && record.teaching_level ? record.teaching_level : 'undergraduate';
            if (startYearEl) startYearEl.value = record && record.start_year ? String(record.start_year) : '';
            if (endYearEl) endYearEl.value = record && record.end_year ? String(record.end_year) : '';

            updateFullTimeFacultyScopeUI((scopeEl && scopeEl.value) || 'inbound');
            updateFullTimeFacultyLevelUI((levelEl && levelEl.value) || 'undergraduate');

            var titleEl = document.getElementById('full-time-foreign-faculty-title');
            if (titleEl) titleEl.textContent = 'Edit Full-Time Foreign Faculty';
            var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
            if (subtitleEl) subtitleEl.textContent = 'Update the record details, then save changes.';
            var saveBtn = document.getElementById('full-time-foreign-faculty-save');
            if (saveBtn) saveBtn.textContent = 'Save Changes';
        }

        function renderFullTimeFacultyDetailRows() {
            var detailBody = document.getElementById('full-time-faculty-detail-body');
            if (!detailBody) return;
            var levelRows = getFullTimeFacultyRowsByLevel(fullTimeFacultyDetailLevel);
            if (!levelRows.length) {
                detailBody.innerHTML = '<tr><td colspan="7" class="py-4 px-2 text-sm text-on-surface-variant">No records in this section.</td></tr>';
                return;
            }
            detailBody.innerHTML = '';
            levelRows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'border-b border-outline-variant/10 last:border-b-0';
                tr.innerHTML =
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(formatStudentScope(r.scope)) + '</td>' +
                    '<td class="py-3 px-2 text-sm font-semibold text-on-surface">' + escapeHtml(r.full_name || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(r.citizenship || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(r.specialization || '—') + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(formatStudentMobilityYear(r.start_year)) + '</td>' +
                    '<td class="py-3 px-2 text-sm text-on-surface-variant">' + escapeHtml(formatStudentMobilityYear(r.end_year)) + '</td>' +
                    '<td class="py-3 px-2 text-sm">' +
                        '<div class="flex items-center gap-2">' +
                            '<button type="button" class="full-time-faculty-detail-edit px-2 py-1 rounded-md bg-surface-container text-on-surface text-xs font-semibold hover:bg-surface-container-high" data-id="' + escapeHtml(String(r.id || '')) + '">Edit</button>' +
                            '<button type="button" class="full-time-faculty-detail-delete px-2 py-1 rounded-md bg-error-container text-on-error-container text-xs font-semibold hover:opacity-90" data-id="' + escapeHtml(String(r.id || '')) + '" data-name="' + escapeHtml(r.full_name || '') + '">Delete</button>' +
                        '</div>' +
                    '</td>';
                detailBody.appendChild(tr);
            });
        }

        function renderFullTimeFaculty(entries) {
            if (!fullTimeFacultyTbody) return;
            fullTimeFacultyTbody.innerHTML = '';
            fullTimeFacultyCache = Array.isArray(entries) ? entries : [];
            if (fullTimeFacultyCache.length === 0) {
                var emptyTr = document.createElement('tr');
                emptyTr.innerHTML = '<td colspan="3" class="py-10 text-center text-on-surface-variant text-sm">No foreign faculty records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                fullTimeFacultyTbody.appendChild(emptyTr);
                return;
            }

            ['undergraduate', 'graduate'].forEach(function (level) {
                var levelRows = getFullTimeFacultyRowsByLevel(level);
                var totalFaculty = levelRows.length;
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors cursor-pointer';
                tr.setAttribute('data-full-time-faculty-summary-level', level);
                tr.setAttribute('tabindex', '0');
                tr.setAttribute('role', 'button');
                tr.setAttribute('aria-label', 'Open ' + formatFullTimeFacultyLevelLabel(level) + ' records');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-semibold text-sm">' + escapeHtml(formatFullTimeFacultyLevelLabel(level)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-sm text-center">' + escapeHtml(String(totalFaculty)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-sm text-center">' + escapeHtml(String(levelRows.length)) + '</td>';
                fullTimeFacultyTbody.appendChild(tr);
            });
        }

        async function loadFullTimeFaculty() {
            if (!fullTimeFacultyTbody) return;
            fullTimeFacultyTbody.innerHTML = '<tr><td colspan="3" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/full-time-foreign-faculty.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderFullTimeFaculty(result.data || []);
            } catch (_) {
                renderFullTimeFaculty([]);
            }
        }

        if (fullTimeFacultyTbody) {
            fullTimeFacultyTbody.addEventListener('click', function (e) {
                var summaryRow = e.target.closest('tr[data-full-time-faculty-summary-level]');
                if (!summaryRow) return;
                var level = summaryRow.getAttribute('data-full-time-faculty-summary-level') || 'undergraduate';
                openFullTimeFacultyDetailModal(level);
            });
            fullTimeFacultyTbody.addEventListener('keydown', function (e) {
                var summaryRow = e.target.closest('tr[data-full-time-faculty-summary-level]');
                if (!summaryRow) return;
                if (e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                var level = summaryRow.getAttribute('data-full-time-faculty-summary-level') || 'undergraduate';
                openFullTimeFacultyDetailModal(level);
            });
        }

        var fullTimeFacultyDetailCloseBtn = document.getElementById('full-time-faculty-detail-close');
        if (fullTimeFacultyDetailCloseBtn) fullTimeFacultyDetailCloseBtn.addEventListener('click', closeFullTimeFacultyDetailModal);
        var fullTimeFacultyDetailBackdrop = document.getElementById('full-time-faculty-detail-backdrop');
        if (fullTimeFacultyDetailBackdrop) fullTimeFacultyDetailBackdrop.addEventListener('click', closeFullTimeFacultyDetailModal);
        var fullTimeFacultyDetailBody = document.getElementById('full-time-faculty-detail-body');
        if (fullTimeFacultyDetailBody) {
            fullTimeFacultyDetailBody.addEventListener('click', async function (e) {
                var editBtn = e.target.closest('.full-time-faculty-detail-edit');
                if (editBtn) {
                    var id = parseInt(editBtn.getAttribute('data-id') || '0', 10);
                    if (!id) return;
                    var record = (fullTimeFacultyCache || []).find(function (r) { return parseInt(String(r.id || '0'), 10) === id; });
                    if (!record) return;
                    closeFullTimeFacultyDetailModal();
                    prefillFullTimeFacultyFormFromRecord(record);
                    return;
                }

                var delBtn = e.target.closest('.full-time-faculty-detail-delete');
                if (!delBtn) return;
                var recordId = delBtn.getAttribute('data-id') || '';
                if (!recordId) return;
                var label = delBtn.getAttribute('data-name') || 'this record';
                var ok = window.confirm('Delete full-time foreign faculty record for "' + label + '"?');
                if (!ok) return;
                try {
                    var resp = await fetch('api/full-time-foreign-faculty.php?id=' + encodeURIComponent(recordId), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadFullTimeFaculty();
                    if (fullTimeFacultyDetailLevel) renderFullTimeFacultyDetailRows();
                } catch (_) {
                    window.alert('Failed to delete full-time foreign faculty record from the server.');
                }
            });
        }

        var fullTimeFacultyCloseBtn = document.getElementById('full-time-foreign-faculty-close');
        if (fullTimeFacultyCloseBtn) fullTimeFacultyCloseBtn.addEventListener('click', closeFullTimeForeignFacultyModal);
        var fullTimeFacultyBackdropEl = document.getElementById('full-time-foreign-faculty-backdrop');
        if (fullTimeFacultyBackdropEl) fullTimeFacultyBackdropEl.addEventListener('click', closeFullTimeForeignFacultyModal);
        var fullTimeFacultyDiscardBtn = document.getElementById('full-time-foreign-faculty-discard');
        if (fullTimeFacultyDiscardBtn) fullTimeFacultyDiscardBtn.addEventListener('click', closeFullTimeForeignFacultyModal);
        var fullTimeFacultyCancelBtn = document.getElementById('full-time-foreign-faculty-cancel');
        if (fullTimeFacultyCancelBtn) fullTimeFacultyCancelBtn.addEventListener('click', closeFullTimeForeignFacultyModal);
        var fullTimeFacultyForm = document.getElementById('full-time-foreign-faculty-form');
        if (fullTimeFacultyForm) {
            fullTimeFacultyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveFullTimeFacultyToDatabase().then(function () {
                    closeFullTimeForeignFacultyModal();
                });
            });
        }
        var fullTimeFacultySaveBtn = document.getElementById('full-time-foreign-faculty-save');
        if (fullTimeFacultySaveBtn) {
            fullTimeFacultySaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveFullTimeFacultyToDatabase().then(function () {
                    closeFullTimeForeignFacultyModal();
                });
            });
        }

        var researchTbody = document.getElementById('research-table-body');

        function formatResearchCategory(category) {
            var c = (category || '').toLowerCase();
            if (c === 'sole') return 'Sole Authorship';
            if (c === 'published') return 'Published Research';
            if (c === 'citations') return 'Citations';
            return 'Collaborative';
        }

        async function saveResearchToDatabase() {
            var facultyEl = document.getElementById('research-faculty-name');
            var yearEl = document.getElementById('research-fiscal-year');
            var titleEl = document.getElementById('research-title-topic');
            var partnerEl = document.getElementById('research-partner-agency');
            var countryEl = document.getElementById('research-partner-country');
            var categoryEl = document.getElementById('research-category-value');
            var publishedEl = document.getElementById('research-published-value');
            var sdgEl = document.getElementById('research-sdg-focus');
            var statusEl = document.querySelector('input[name="research-status"]:checked');

            var facultyName = facultyEl ? (facultyEl.value || '').trim() : '';
            var fiscalYearRaw = yearEl ? (yearEl.value || '').trim() : '';
            var researchTitle = titleEl ? (titleEl.value || '').trim() : '';
            var partnerAgency = partnerEl ? (partnerEl.value || '').trim() : '';
            var partnerCountry = countryEl ? (countryEl.value || '').trim() : '';
            var category = categoryEl ? (categoryEl.value || '').trim() : 'collaborative';
            var publishedStatus = publishedEl ? (publishedEl.value || '').trim() : 'published';
            var projectStatus = statusEl ? (statusEl.value || '').trim() : 'ongoing';
            var sdgFocus = sdgEl ? (sdgEl.value || '').trim() : '';

            var toast = function (message, isError) {
                var el = document.createElement('div');
                el.className = 'fixed top-4 right-4 z-[999] px-5 py-3 rounded-lg shadow-lg text-sm font-bold ' +
                    (isError ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(function () { if (el && el.parentNode) el.parentNode.removeChild(el); }, 3000);
            };

            var fiscalYear = fiscalYearRaw === '' ? null : parseInt(fiscalYearRaw, 10);
            if (!facultyName || !researchTitle) {
                toast('Please complete Faculty Name and Research Title/Topic.', true);
                throw new Error('Validation failed');
            }
            if (fiscalYear !== null && (isNaN(fiscalYear) || fiscalYear < 1900 || fiscalYear > 2100)) {
                toast('Please provide a valid Fiscal Year (1900-2100).', true);
                throw new Error('Validation failed');
            }

            var formData = new FormData();
            formData.append('category', category);
            formData.append('faculty_name', facultyName);
            formData.append('fiscal_year', fiscalYear === null ? '' : String(fiscalYear));
            formData.append('research_title', researchTitle);
            formData.append('partner_agency', partnerAgency);
            formData.append('partner_country', partnerCountry);
            formData.append('project_status', projectStatus);
            formData.append('published_status', publishedStatus);
            formData.append('sdg_focus', sdgFocus);

            var endpoint = editingResearchId
                ? ('api/internationalization-research.php?action=update&id=' + encodeURIComponent(String(editingResearchId)))
                : 'api/internationalization-research.php';
            var response = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await response.json();
            if (!response.ok || !result || !result.success) {
                var err = (result && result.error) ? result.error : 'Failed to save research record';
                toast(err, true);
                throw new Error(err);
            }
            if (typeof loadResearchRecords === 'function') await loadResearchRecords();
            toast(result.message || 'Research record saved.', false);
            return result;
        }

        function renderResearchRecords(entries) {
            if (!researchTbody) return;
            researchTbody.innerHTML = '';
            var rows = entries || [];
            if (rows.length === 0) {
                var emptyTr = document.createElement('tr');
                emptyTr.innerHTML = '<td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">No research records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                researchTbody.appendChild(emptyTr);
                return;
            }
            rows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-research-id', String(r.id || ''));
                tr.setAttribute('data-research-category', r.category || 'collaborative');
                tr.setAttribute('data-research-faculty', r.faculty_name || '');
                tr.setAttribute('data-research-year', r.fiscal_year || '');
                tr.setAttribute('data-research-title', r.research_title || '');
                tr.setAttribute('data-research-partner', r.partner_agency || '');
                tr.setAttribute('data-research-country', r.partner_country || '');
                tr.setAttribute('data-research-status', r.project_status || 'ongoing');
                tr.setAttribute('data-research-published', r.published_status || 'not-published');
                tr.setAttribute('data-research-sdg', r.sdg_focus || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatResearchCategory(r.category)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.faculty_name || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.fiscal_year)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.research_title || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.partner_agency || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.partner_country || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.project_status || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.published_status || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.sdg_focus || '—') + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="research-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0" aria-label="Row actions">more_vert</span></td>';
                researchTbody.appendChild(tr);
            });
        }

        async function loadResearchRecords() {
            if (!researchTbody) return;
            researchTbody.innerHTML = '<tr><td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/internationalization-research.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderResearchRecords(result.data || []);
            } catch (_) {
                renderResearchRecords([]);
            }
        }

        if (researchTbody) {
            var activeResearchRow = null;
            var researchMenu = document.getElementById('research-row-actions-menu');
            if (!researchMenu) {
                researchMenu = document.createElement('div');
                researchMenu.id = 'research-row-actions-menu';
                researchMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                researchMenu.setAttribute('role', 'menu');
                researchMenu.innerHTML =
                    '<button type="button" id="research-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Edit</button>' +
                    '<button type="button" id="research-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Delete</button>';
                document.body.appendChild(researchMenu);
            }

            var closeResearchMenu = function () {
                if (researchMenu) researchMenu.classList.add('hidden');
                activeResearchRow = null;
            };
            var openResearchMenuForBtn = function (btn) {
                activeResearchRow = btn ? btn.closest('tr') : null;
                if (!activeResearchRow) return;
                var rect = btn.getBoundingClientRect();
                researchMenu.style.left = Math.max(12, rect.left) + 'px';
                researchMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                researchMenu.classList.remove('hidden');
            };

            researchTbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.research-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openResearchMenuForBtn(btn);
            });
            researchTbody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.research-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openResearchMenuForBtn(btn);
                }
            });

            var researchEditBtn = document.getElementById('research-row-edit-btn');
            if (researchEditBtn) {
                researchEditBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!activeResearchRow) return;
                    editingResearchId = parseInt(activeResearchRow.getAttribute('data-research-id') || '', 10) || null;
                    openInternationalizationResearchModal();

                    var categoryEl = document.getElementById('research-category-value');
                    var facultyEl = document.getElementById('research-faculty-name');
                    var yearEl = document.getElementById('research-fiscal-year');
                    var titleEl = document.getElementById('research-title-topic');
                    var partnerEl = document.getElementById('research-partner-agency');
                    var countryEl = document.getElementById('research-partner-country');
                    var sdgEl = document.getElementById('research-sdg-focus');
                    var publishedEl = document.getElementById('research-published-value');
                    var status = activeResearchRow.getAttribute('data-research-status') || 'ongoing';

                    if (categoryEl) categoryEl.value = activeResearchRow.getAttribute('data-research-category') || 'collaborative';
                    if (facultyEl) facultyEl.value = activeResearchRow.getAttribute('data-research-faculty') || '';
                    if (yearEl) yearEl.value = activeResearchRow.getAttribute('data-research-year') || '';
                    if (titleEl) titleEl.value = activeResearchRow.getAttribute('data-research-title') || '';
                    if (partnerEl) partnerEl.value = activeResearchRow.getAttribute('data-research-partner') || '';
                    if (countryEl) countryEl.value = activeResearchRow.getAttribute('data-research-country') || '';
                    if (sdgEl) sdgEl.value = activeResearchRow.getAttribute('data-research-sdg') || '';
                    if (publishedEl) publishedEl.value = activeResearchRow.getAttribute('data-research-published') || 'not-published';
                    var ongoing = document.querySelector('input[name="research-status"][value="ongoing"]');
                    var completed = document.querySelector('input[name="research-status"][value="completed"]');
                    if (ongoing) ongoing.checked = status !== 'completed';
                    if (completed) completed.checked = status === 'completed';

                    updateResearchCategoryUI((categoryEl && categoryEl.value) || 'collaborative');
                    updateResearchPublishedUI((publishedEl && publishedEl.value) === 'published');

                    var titleModal = document.getElementById('internationalization-research-title');
                    if (titleModal) titleModal.textContent = 'Edit Internationalization of Research';
                    var subtitleEl = titleModal ? titleModal.nextElementSibling : null;
                    if (subtitleEl) subtitleEl.textContent = 'Update the record details, then save changes.';
                    var saveBtn = document.getElementById('research-save-record');
                    if (saveBtn) saveBtn.innerHTML = '<span class="material-symbols-outlined text-base">save</span>SAVE CHANGES';
                    closeResearchMenu();
                });
            }

            var researchDeleteBtn = document.getElementById('research-row-delete-btn');
            if (researchDeleteBtn) {
                researchDeleteBtn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    if (!activeResearchRow) return;
                    var recordId = activeResearchRow.getAttribute('data-research-id');
                    if (!recordId) return;
                    var label = activeResearchRow.getAttribute('data-research-title') || 'this record';
                    var ok = window.confirm('Delete research record "' + label + '"?');
                    if (!ok) return;
                    try {
                        var resp = await fetch('api/internationalization-research.php?id=' + encodeURIComponent(recordId), { method: 'DELETE', credentials: 'same-origin' });
                        var result = await resp.json();
                        if (!resp.ok || !result || !result.success) throw new Error();
                        await loadResearchRecords();
                    } catch (_) {
                        window.alert('Failed to delete research record from the server.');
                    }
                    closeResearchMenu();
                });
            }

            document.addEventListener('click', function () { closeResearchMenu(); });
            window.addEventListener('scroll', function () { closeResearchMenu(); }, true);
        }

        var researchCloseBtn = document.getElementById('internationalization-research-close');
        if (researchCloseBtn) researchCloseBtn.addEventListener('click', closeInternationalizationResearchModal);
        var researchBackdropEl = document.getElementById('internationalization-research-backdrop');
        if (researchBackdropEl) researchBackdropEl.addEventListener('click', closeInternationalizationResearchModal);
        var researchCancelBtn = document.getElementById('research-cancel');
        if (researchCancelBtn) researchCancelBtn.addEventListener('click', closeInternationalizationResearchModal);
        var researchDraftBtn = document.getElementById('research-save-draft');
        if (researchDraftBtn) researchDraftBtn.addEventListener('click', closeInternationalizationResearchModal);
        var researchForm = document.getElementById('internationalization-research-form');
        if (researchForm) {
            researchForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveResearchToDatabase().then(function () {
                    closeInternationalizationResearchModal();
                });
            });
        }
        var researchSaveBtn = document.getElementById('research-save-record');
        if (researchSaveBtn) {
            researchSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveResearchToDatabase().then(function () {
                    closeInternationalizationResearchModal();
                });
            });
        }

        var tnTableBody = document.getElementById('tn-table-body');
        var coilTableBody = document.getElementById('coil-table-body');

        function getTnNationalitiesText() {
            var wrap = document.getElementById('tn-nationalities');
            if (!wrap) return '';
            var items = Array.prototype.slice.call(wrap.querySelectorAll('.tn-nationality-chip')).map(function (chip) {
                return (chip.firstChild && chip.firstChild.textContent ? chip.firstChild.textContent : '').trim();
            }).filter(function (x) { return x; });
            return items.join(', ');
        }

        async function saveTnToDatabase() {
            var partnerEl = document.getElementById('tn-partner-university');
            var countryEl = document.getElementById('tn-country');
            var yearEl = document.getElementById('tn-year-started');
            var programEl = document.getElementById('tn-academic-program');
            var permitEl = document.getElementById('tn-ched-permit');
            var studentsEl = document.getElementById('tn-students');
            var statusEl = document.getElementById('tn-program-status');
            var nationalityInputEl = document.getElementById('tn-nationality-input');

            var partner = partnerEl ? (partnerEl.value || '').trim() : '';
            var country = countryEl ? (countryEl.value || '').trim() : '';
            var yearRaw = yearEl ? (yearEl.value || '').trim() : '';
            var program = programEl ? (programEl.value || '').trim() : '';
            var permit = permitEl && permitEl.files && permitEl.files[0] ? permitEl.files[0].name : '';
            var studentsRaw = studentsEl ? (studentsEl.value || '').trim() : '';
            var status = statusEl ? (statusEl.value || '').trim() : '';
            var nationalities = getTnNationalitiesText();
            var pendingNationality = nationalityInputEl ? (nationalityInputEl.value || '').trim() : '';
            if (pendingNationality) nationalities = nationalities ? (nationalities + ', ' + pendingNationality) : pendingNationality;

            var year = yearRaw === '' ? null : parseInt(yearRaw, 10);
            var students = studentsRaw === '' ? 0 : parseInt(studentsRaw, 10);
            if (!partner || !country || !program) throw new Error('Please complete Partner University, Country, and Academic Program.');
            if (year !== null && (isNaN(year) || year < 1900 || year > 2100)) throw new Error('Please provide a valid Year Started (1900-2100).');
            if (isNaN(students) || students < 0) throw new Error('Number of Students must be 0 or greater.');

            var formData = new FormData();
            formData.append('partner_university', partner);
            formData.append('country', country);
            formData.append('academic_program', program);
            formData.append('ched_permit', permit);
            formData.append('students_count', String(students));
            formData.append('nationalities', nationalities);
            formData.append('year_started', year === null ? '' : String(year));
            formData.append('program_status', status);

            var endpoint = editingTnId ? ('api/transnational-education-program.php?action=update&id=' + encodeURIComponent(String(editingTnId))) : 'api/transnational-education-program.php';
            var resp = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await resp.json();
            if (!resp.ok || !result || !result.success) throw new Error((result && result.error) || 'Failed to save record');
            await loadTnRecords();
        }

        function renderTnRecords(rows) {
            if (!tnTableBody) return;
            tnTableBody.innerHTML = '';
            var entries = rows || [];
            if (!entries.length) {
                tnTableBody.innerHTML = '<tr><td colspan="8" class="py-10 text-center text-on-surface-variant text-sm">No transnational education records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td></tr>';
                return;
            }
            entries.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-tn-id', String(r.id || ''));
                tr.setAttribute('data-tn-partner', r.partner_university || '');
                tr.setAttribute('data-tn-country', r.country || '');
                tr.setAttribute('data-tn-program', r.academic_program || '');
                tr.setAttribute('data-tn-permit', r.ched_permit || '');
                tr.setAttribute('data-tn-students', String(r.students_count || 0));
                tr.setAttribute('data-tn-nationalities', r.nationalities || '');
                tr.setAttribute('data-tn-year', r.year_started || '');
                tr.setAttribute('data-tn-status', r.program_status || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.partner_university || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.country || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.academic_program || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.ched_permit || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(String(r.students_count || 0)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.nationalities || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.year_started)) + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="tn-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0">more_vert</span></td>';
                tnTableBody.appendChild(tr);
            });
        }

        async function loadTnRecords() {
            if (!tnTableBody) return;
            tnTableBody.innerHTML = '<tr><td colspan="8" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/transnational-education-program.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderTnRecords(result.data || []);
            } catch (_) {
                renderTnRecords([]);
            }
        }

        async function saveCoilToDatabase() {
            var partnerEl = document.getElementById('coil-partner-university');
            var countryEl = document.getElementById('coil-country');
            var yearEl = document.getElementById('coil-year');
            var subjectEl = document.getElementById('coil-subject');
            var partner = partnerEl ? (partnerEl.value || '').trim() : '';
            var country = countryEl ? (countryEl.value || '').trim() : '';
            var yearRaw = yearEl ? (yearEl.value || '').trim() : '';
            var subject = subjectEl ? (subjectEl.value || '').trim() : '';
            var year = yearRaw === '' ? null : parseInt(yearRaw, 10);
            if (!partner || !country) throw new Error('Please complete Partner University and Country.');
            if (year !== null && (isNaN(year) || year < 1900 || year > 2100)) throw new Error('Please provide a valid year (1900-2100).');

            var formData = new FormData();
            formData.append('partner_university', partner);
            formData.append('country', country);
            formData.append('coil_subject', subject);
            formData.append('year', year === null ? '' : String(year));
            var endpoint = editingCoilId ? ('api/coil-classes.php?action=update&id=' + encodeURIComponent(String(editingCoilId))) : 'api/coil-classes.php';
            var resp = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await resp.json();
            if (!resp.ok || !result || !result.success) throw new Error((result && result.error) || 'Failed to save record');
            await loadCoilRecords();
        }

        function renderCoilRecords(rows) {
            if (!coilTableBody) return;
            coilTableBody.innerHTML = '';
            var entries = rows || [];
            if (!entries.length) {
                coilTableBody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-on-surface-variant text-sm">No COIL records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td></tr>';
                return;
            }
            entries.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-coil-id', String(r.id || ''));
                tr.setAttribute('data-coil-partner', r.partner_university || '');
                tr.setAttribute('data-coil-country', r.country || '');
                tr.setAttribute('data-coil-subject', r.coil_subject || '');
                tr.setAttribute('data-coil-year', r.year || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.partner_university || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.country || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.coil_subject || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.year)) + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="coil-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0">more_vert</span></td>';
                coilTableBody.appendChild(tr);
            });
        }

        async function loadCoilRecords() {
            if (!coilTableBody) return;
            coilTableBody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/coil-classes.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderCoilRecords(result.data || []);
            } catch (_) {
                renderCoilRecords([]);
            }
        }

        if (tnTableBody) {
            var activeTnRow = null;
            var tnMenu = document.getElementById('tn-row-actions-menu');
            if (!tnMenu) {
                tnMenu = document.createElement('div');
                tnMenu.id = 'tn-row-actions-menu';
                tnMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                tnMenu.innerHTML = '<button type="button" id="tn-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Edit</button><button type="button" id="tn-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Delete</button>';
                document.body.appendChild(tnMenu);
            }
            var closeTnMenu = function () { tnMenu.classList.add('hidden'); activeTnRow = null; };
            var openTnMenu = function (btn) {
                activeTnRow = btn.closest('tr');
                if (!activeTnRow) return;
                var rect = btn.getBoundingClientRect();
                tnMenu.style.left = Math.max(12, rect.left) + 'px';
                tnMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                tnMenu.classList.remove('hidden');
            };
            tnTableBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.tn-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openTnMenu(btn);
            });
            tnTableBody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.tn-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openTnMenu(btn); }
            });
            var tnEditBtn = document.getElementById('tn-row-edit-btn');
            if (tnEditBtn) tnEditBtn.addEventListener('click', function () {
                if (!activeTnRow) return;
                editingTnId = parseInt(activeTnRow.getAttribute('data-tn-id') || '', 10) || null;
                openTransnationalEducationProgramModal();
                var partnerEl = document.getElementById('tn-partner-university');
                var countryEl = document.getElementById('tn-country');
                var yearEl = document.getElementById('tn-year-started');
                var programEl = document.getElementById('tn-academic-program');
                var studentsEl = document.getElementById('tn-students');
                var statusEl = document.getElementById('tn-program-status');
                var natWrap = document.getElementById('tn-nationalities');
                if (partnerEl) partnerEl.value = activeTnRow.getAttribute('data-tn-partner') || '';
                if (countryEl) countryEl.value = activeTnRow.getAttribute('data-tn-country') || '';
                if (yearEl) yearEl.value = activeTnRow.getAttribute('data-tn-year') || '';
                if (programEl) programEl.value = activeTnRow.getAttribute('data-tn-program') || '';
                if (studentsEl) studentsEl.value = activeTnRow.getAttribute('data-tn-students') || '0';
                if (statusEl) statusEl.value = activeTnRow.getAttribute('data-tn-status') || '';
                if (natWrap) {
                    Array.prototype.slice.call(natWrap.querySelectorAll('.tn-nationality-chip')).forEach(function (el) { el.remove(); });
                    var list = (activeTnRow.getAttribute('data-tn-nationalities') || '').split(',').map(function (x) { return x.trim(); }).filter(function (x) { return x; });
                    var natInput = document.getElementById('tn-nationality-input');
                    list.forEach(function (nat) {
                        var chip = document.createElement('span');
                        chip.className = 'bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 tn-nationality-chip';
                        chip.innerHTML = '<span>' + escapeHtml(nat) + '</span><button type="button" class="tn-chip-remove p-0.5 rounded hover:bg-primary/10" aria-label="Remove nationality"><span class="material-symbols-outlined text-xs">close</span></button>';
                        natWrap.insertBefore(chip, natInput || null);
                    });
                }
                var titleEl = document.getElementById('transnational-education-program-modal-title');
                if (titleEl) titleEl.textContent = 'Edit Transnational Education Program';
                var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                if (subtitleEl) subtitleEl.textContent = 'Update this transnational record and save changes.';
                var saveBtn = document.getElementById('tn-program-save');
                if (saveBtn) saveBtn.textContent = 'Save Changes';
                closeTnMenu();
            });
            var tnDeleteBtn = document.getElementById('tn-row-delete-btn');
            if (tnDeleteBtn) tnDeleteBtn.addEventListener('click', async function () {
                if (!activeTnRow) return;
                var id = activeTnRow.getAttribute('data-tn-id');
                if (!id) return;
                if (!window.confirm('Delete this transnational education record?')) return;
                try {
                    var resp = await fetch('api/transnational-education-program.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadTnRecords();
                } catch (_) {
                    window.alert('Failed to delete transnational education record.');
                }
                closeTnMenu();
            });
            document.addEventListener('click', function () { closeTnMenu(); });
            window.addEventListener('scroll', function () { closeTnMenu(); }, true);
        }

        if (coilTableBody) {
            var activeCoilRow = null;
            var coilMenu = document.getElementById('coil-row-actions-menu');
            if (!coilMenu) {
                coilMenu = document.createElement('div');
                coilMenu.id = 'coil-row-actions-menu';
                coilMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                coilMenu.innerHTML = '<button type="button" id="coil-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Edit</button><button type="button" id="coil-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Delete</button>';
                document.body.appendChild(coilMenu);
            }
            var closeCoilMenu = function () { coilMenu.classList.add('hidden'); activeCoilRow = null; };
            var openCoilMenu = function (btn) {
                activeCoilRow = btn.closest('tr');
                if (!activeCoilRow) return;
                var rect = btn.getBoundingClientRect();
                coilMenu.style.left = Math.max(12, rect.left) + 'px';
                coilMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                coilMenu.classList.remove('hidden');
            };
            coilTableBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.coil-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openCoilMenu(btn);
            });
            coilTableBody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.coil-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCoilMenu(btn); }
            });
            var coilEditBtn = document.getElementById('coil-row-edit-btn');
            if (coilEditBtn) coilEditBtn.addEventListener('click', function () {
                if (!activeCoilRow) return;
                editingCoilId = parseInt(activeCoilRow.getAttribute('data-coil-id') || '', 10) || null;
                openCoilModal();
                var partnerEl = document.getElementById('coil-partner-university');
                var countryEl = document.getElementById('coil-country');
                var yearEl = document.getElementById('coil-year');
                var subjectEl = document.getElementById('coil-subject');
                if (partnerEl) partnerEl.value = activeCoilRow.getAttribute('data-coil-partner') || '';
                if (countryEl) countryEl.value = activeCoilRow.getAttribute('data-coil-country') || '';
                if (yearEl) yearEl.value = activeCoilRow.getAttribute('data-coil-year') || '';
                if (subjectEl) subjectEl.value = activeCoilRow.getAttribute('data-coil-subject') || '';
                var titleEl = document.getElementById('coil-entry-modal-title');
                if (titleEl) titleEl.textContent = 'Edit COIL Class';
                var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                if (subtitleEl) subtitleEl.textContent = 'Update this COIL class record and save changes.';
                var saveBtn = document.getElementById('coil-entry-save');
                if (saveBtn) saveBtn.textContent = 'Save Changes';
                closeCoilMenu();
            });
            var coilDeleteBtn = document.getElementById('coil-row-delete-btn');
            if (coilDeleteBtn) coilDeleteBtn.addEventListener('click', async function () {
                if (!activeCoilRow) return;
                var id = activeCoilRow.getAttribute('data-coil-id');
                if (!id) return;
                if (!window.confirm('Delete this COIL record?')) return;
                try {
                    var resp = await fetch('api/coil-classes.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadCoilRecords();
                } catch (_) {
                    window.alert('Failed to delete COIL record.');
                }
                closeCoilMenu();
            });
            document.addEventListener('click', function () { closeCoilMenu(); });
            window.addEventListener('scroll', function () { closeCoilMenu(); }, true);
        }

        var tnCloseBtn = document.getElementById('transnational-education-program-modal-close');
        if (tnCloseBtn) tnCloseBtn.addEventListener('click', closeTransnationalEducationProgramModal);
        var tnBackdropEl = document.getElementById('transnational-education-program-modal-backdrop');
        if (tnBackdropEl) tnBackdropEl.addEventListener('click', closeTransnationalEducationProgramModal);
        var tnCancelBtn = document.getElementById('tn-program-cancel');
        if (tnCancelBtn) tnCancelBtn.addEventListener('click', closeTransnationalEducationProgramModal);
        var tnForm = document.getElementById('transnational-education-program-form');
        if (tnForm) {
            tnForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveTnToDatabase().then(function () { closeTransnationalEducationProgramModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }
        var tnSaveBtn = document.getElementById('tn-program-save');
        if (tnSaveBtn) {
            tnSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveTnToDatabase().then(function () { closeTransnationalEducationProgramModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }

        var ceaTableBody = document.getElementById('cea-table-body');

        function getCeaPartnersText() {
            var wrap = document.getElementById('cea-partners');
            if (!wrap) return '';
            return Array.prototype.slice.call(wrap.querySelectorAll('.cea-partner-chip .cea-partner-name')).map(function (el) {
                return (el.textContent || '').trim();
            }).filter(function (x) { return x; }).join(', ');
        }

        function setCeaPartnersFromCsv(csv) {
            var wrap = document.getElementById('cea-partners');
            if (!wrap) return;
            Array.prototype.slice.call(wrap.querySelectorAll('.cea-partner-chip')).forEach(function (chip) { chip.remove(); });
            var names = String(csv || '').split(',').map(function (x) { return x.trim(); }).filter(function (x) { return x; });
            names.forEach(function (value) {
                var chip = document.createElement('span');
                chip.className = 'bg-surface-container text-[11px] font-bold text-on-surface px-3 py-1 rounded-full flex items-center gap-2 cea-partner-chip';
                chip.innerHTML = '<span class="cea-partner-name">' + escapeHtml(value) + '</span>' +
                    '<button type="button" class="cea-partner-remove p-0.5 rounded hover:bg-surface-container-high" aria-label="Remove partner">' +
                    '<span class="material-symbols-outlined text-[14px] text-on-surface">close</span>' +
                    '</button>';
                wrap.appendChild(chip);
            });
        }

        async function saveCollaborativeEventToDatabase() {
            var eventEl = document.getElementById('cea-event-name');
            var countryEl = document.getElementById('cea-country');
            var participantsEl = document.getElementById('cea-participants');
            var yearEl = document.getElementById('cea-year');
            var eventName = eventEl ? (eventEl.value || '').trim() : '';
            var partners = getCeaPartnersText();
            var country = countryEl ? (countryEl.value || '').trim() : '';
            var yearRaw = yearEl ? (yearEl.value || '').trim() : '';
            var participantsRaw = participantsEl ? (participantsEl.value || '').trim() : '0';
            var participants = participantsRaw === '' ? 0 : parseInt(participantsRaw, 10);
            var year = yearRaw === '' ? null : parseInt(yearRaw, 10);

            if (!eventName) throw new Error('Collaborative event/activity is required.');
            if (isNaN(participants) || participants < 0) throw new Error('Participants must be 0 or greater.');
            if (year !== null && (isNaN(year) || year < 1900 || year > 2100)) throw new Error('Please provide a valid year.');

            var formData = new FormData();
            formData.append('event_name', eventName);
            formData.append('partners', partners);
            formData.append('countries', country);
            formData.append('participants', String(participants));
            formData.append('year', year === null ? '' : String(year));

            var endpoint = editingCeaId ? ('api/collaborative-events-activities.php?action=update&id=' + encodeURIComponent(String(editingCeaId))) : 'api/collaborative-events-activities.php';
            var resp = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await resp.json();
            if (!resp.ok || !result || !result.success) throw new Error((result && result.error) || 'Failed to save record');
            await loadCollaborativeEvents();
        }

        function renderCollaborativeEvents(rows) {
            if (!ceaTableBody) return;
            ceaTableBody.innerHTML = '';
            var entries = rows || [];
            if (!entries.length) {
                ceaTableBody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">No collaborative events yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td></tr>';
                return;
            }
            entries.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-cea-id', String(r.id || ''));
                tr.setAttribute('data-cea-event', r.event_name || '');
                tr.setAttribute('data-cea-partners', r.partners || '');
                tr.setAttribute('data-cea-countries', r.countries || '');
                tr.setAttribute('data-cea-participants', String(r.participants || 0));
                tr.setAttribute('data-cea-year', r.year || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.event_name || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.partners || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.countries || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(String(r.participants || 0)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.year)) + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="cea-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0">more_vert</span></td>';
                ceaTableBody.appendChild(tr);
            });
        }

        async function loadCollaborativeEvents() {
            if (!ceaTableBody) return;
            ceaTableBody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/collaborative-events-activities.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderCollaborativeEvents(result.data || []);
            } catch (_) {
                renderCollaborativeEvents([]);
            }
        }

        if (ceaTableBody) {
            var activeCeaRow = null;
            var ceaMenu = document.getElementById('cea-row-actions-menu');
            if (!ceaMenu) {
                ceaMenu = document.createElement('div');
                ceaMenu.id = 'cea-row-actions-menu';
                ceaMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                ceaMenu.innerHTML = '<button type="button" id="cea-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Edit</button><button type="button" id="cea-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Delete</button>';
                document.body.appendChild(ceaMenu);
            }
            var closeCeaMenu = function () { ceaMenu.classList.add('hidden'); activeCeaRow = null; };
            var openCeaMenu = function (btn) {
                activeCeaRow = btn.closest('tr');
                if (!activeCeaRow) return;
                var rect = btn.getBoundingClientRect();
                ceaMenu.style.left = Math.max(12, rect.left) + 'px';
                ceaMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                ceaMenu.classList.remove('hidden');
            };
            ceaTableBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.cea-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openCeaMenu(btn);
            });
            ceaTableBody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.cea-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openCeaMenu(btn); }
            });
            var ceaEditBtn = document.getElementById('cea-row-edit-btn');
            if (ceaEditBtn) ceaEditBtn.addEventListener('click', function () {
                if (!activeCeaRow) return;
                editingCeaId = parseInt(activeCeaRow.getAttribute('data-cea-id') || '', 10) || null;
                openCollaborativeEventsActivitiesModal();
                var eventEl = document.getElementById('cea-event-name');
                var countryEl = document.getElementById('cea-country');
                var participantsEl = document.getElementById('cea-participants');
                var yearEl = document.getElementById('cea-year');
                if (eventEl) eventEl.value = activeCeaRow.getAttribute('data-cea-event') || '';
                if (countryEl) countryEl.value = activeCeaRow.getAttribute('data-cea-countries') || '';
                if (participantsEl) participantsEl.value = activeCeaRow.getAttribute('data-cea-participants') || '0';
                if (yearEl) yearEl.value = activeCeaRow.getAttribute('data-cea-year') || '';
                setCeaPartnersFromCsv(activeCeaRow.getAttribute('data-cea-partners') || '');
                var titleEl = document.getElementById('collaborative-events-activities-modal-title');
                if (titleEl) titleEl.textContent = 'Edit Collaborative Event/Activity';
                var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                if (subtitleEl) subtitleEl.textContent = 'Update the event details, then save changes.';
                var saveBtn = document.getElementById('cea-save');
                if (saveBtn) saveBtn.textContent = 'Save Changes';
                closeCeaMenu();
            });
            var ceaDeleteBtn = document.getElementById('cea-row-delete-btn');
            if (ceaDeleteBtn) ceaDeleteBtn.addEventListener('click', async function () {
                if (!activeCeaRow) return;
                var id = activeCeaRow.getAttribute('data-cea-id');
                if (!id) return;
                if (!window.confirm('Delete this collaborative event/activity record?')) return;
                try {
                    var resp = await fetch('api/collaborative-events-activities.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadCollaborativeEvents();
                } catch (_) {
                    window.alert('Failed to delete collaborative event record.');
                }
                closeCeaMenu();
            });
            document.addEventListener('click', function () { closeCeaMenu(); });
            window.addEventListener('scroll', function () { closeCeaMenu(); }, true);
        }

        var collabCloseBtn = document.getElementById('collaborative-events-activities-modal-close');
        if (collabCloseBtn) collabCloseBtn.addEventListener('click', closeCollaborativeEventsActivitiesModal);
        var collabBackdropEl = document.getElementById('collaborative-events-activities-modal-backdrop');
        if (collabBackdropEl) collabBackdropEl.addEventListener('click', closeCollaborativeEventsActivitiesModal);
        var collabCancelBtn = document.getElementById('cea-cancel');
        if (collabCancelBtn) collabCancelBtn.addEventListener('click', closeCollaborativeEventsActivitiesModal);
        var collabForm = document.getElementById('collaborative-events-activities-form');
        if (collabForm) {
            collabForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveCollaborativeEventToDatabase().then(function () { closeCollaborativeEventsActivitiesModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }
        var ceaSaveBtn = document.getElementById('cea-save');
        if (ceaSaveBtn) {
            ceaSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveCollaborativeEventToDatabase().then(function () { closeCollaborativeEventsActivitiesModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }

        var inhouseTableBody = document.getElementById('inhouse-table-body');

        function getInhousePartnersText() {
            var wrap = document.getElementById('inhouse-partners');
            if (!wrap) return '';
            return Array.prototype.slice.call(wrap.querySelectorAll('.inhouse-partner-chip .inhouse-partner-name')).map(function (el) {
                return (el.textContent || '').trim();
            }).filter(function (x) { return x; }).join(', ');
        }

        function getInhouseCountriesText() {
            var wrap = document.getElementById('inhouse-countries');
            if (!wrap) return '';
            return Array.prototype.slice.call(wrap.querySelectorAll('.inhouse-country-chip .inhouse-country-name')).map(function (el) {
                return (el.textContent || '').trim();
            }).filter(function (x) { return x; }).join(', ');
        }

        function setInhousePartnersFromCsv(csv) {
            var wrap = document.getElementById('inhouse-partners');
            if (!wrap) return;
            Array.prototype.slice.call(wrap.querySelectorAll('.inhouse-partner-chip')).forEach(function (chip) { chip.remove(); });
            var names = String(csv || '').split(',').map(function (x) { return x.trim(); }).filter(function (x) { return x; });
            names.forEach(function (value) {
                var chip = document.createElement('span');
                chip.className = 'bg-primary-container text-on-primary-container text-[11px] font-bold px-3 py-1 rounded-full flex items-center gap-2 inhouse-partner-chip';
                chip.innerHTML = '<span class="inhouse-partner-name">' + escapeHtml(value) + '</span>' +
                    '<button type="button" class="inhouse-partner-remove p-0.5 rounded hover:opacity-90" aria-label="Remove partner">' +
                    '<span class="material-symbols-outlined text-[14px]">close</span>' +
                    '</button>';
                wrap.appendChild(chip);
            });
        }

        function setInhouseCountriesFromCsv(csv) {
            var wrap = document.getElementById('inhouse-countries');
            if (!wrap) return;
            Array.prototype.slice.call(wrap.querySelectorAll('.inhouse-country-chip')).forEach(function (chip) { chip.remove(); });
            var names = String(csv || '').split(',').map(function (x) { return x.trim(); }).filter(function (x) { return x; });
            names.forEach(function (value) {
                var chip = document.createElement('span');
                chip.className = 'bg-secondary-container text-on-secondary-container text-[11px] font-bold px-3 py-1 rounded-full flex items-center gap-2 inhouse-country-chip';
                chip.innerHTML = '<span class="inhouse-country-name">' + escapeHtml(value) + '</span>' +
                    '<button type="button" class="inhouse-country-remove p-0.5 rounded hover:opacity-90" aria-label="Remove country">' +
                    '<span class="material-symbols-outlined text-[14px]">close</span>' +
                    '</button>';
                wrap.appendChild(chip);
            });
        }

        async function saveInhouseEventToDatabase() {
            var eventEl = document.getElementById('inhouse-event-name');
            var participantsEl = document.getElementById('inhouse-participants');
            var yearEl = document.getElementById('inhouse-year');
            var eventName = eventEl ? (eventEl.value || '').trim() : '';
            var partners = getInhousePartnersText();
            var countries = getInhouseCountriesText();
            var participantsRaw = participantsEl ? (participantsEl.value || '').trim() : '0';
            var yearRaw = yearEl ? (yearEl.value || '').trim() : '';
            var participants = participantsRaw === '' ? 0 : parseInt(participantsRaw, 10);
            var year = yearRaw === '' ? null : parseInt(yearRaw, 10);
            if (!eventName) throw new Error('Event name is required.');
            if (isNaN(participants) || participants < 0) throw new Error('Participants must be 0 or greater.');
            if (year !== null && (isNaN(year) || year < 1900 || year > 2100)) throw new Error('Please provide a valid year.');

            var formData = new FormData();
            formData.append('event_name', eventName);
            formData.append('partners', partners);
            formData.append('countries', countries);
            formData.append('participants', String(participants));
            formData.append('year', year === null ? '' : String(year));

            var endpoint = editingInhouseId ? ('api/in-house-asean-events.php?action=update&id=' + encodeURIComponent(String(editingInhouseId))) : 'api/in-house-asean-events.php';
            var resp = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await resp.json();
            if (!resp.ok || !result || !result.success) throw new Error((result && result.error) || 'Failed to save record');
            await loadInhouseEvents();
        }

        function renderInhouseEvents(rows) {
            if (!inhouseTableBody) return;
            inhouseTableBody.innerHTML = '';
            var entries = rows || [];
            if (!entries.length) {
                inhouseTableBody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">No in-house ASEAN events yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td></tr>';
                return;
            }
            entries.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-inhouse-id', String(r.id || ''));
                tr.setAttribute('data-inhouse-event', r.event_name || '');
                tr.setAttribute('data-inhouse-partners', r.partners || '');
                tr.setAttribute('data-inhouse-countries', r.countries || '');
                tr.setAttribute('data-inhouse-participants', String(r.participants || 0));
                tr.setAttribute('data-inhouse-year', r.year || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.event_name || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.partners || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.countries || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(String(r.participants || 0)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.year)) + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="inhouse-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0">more_vert</span></td>';
                inhouseTableBody.appendChild(tr);
            });
        }

        async function loadInhouseEvents() {
            if (!inhouseTableBody) return;
            inhouseTableBody.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/in-house-asean-events.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderInhouseEvents(result.data || []);
            } catch (_) {
                renderInhouseEvents([]);
            }
        }

        if (inhouseTableBody) {
            var activeInhouseRow = null;
            var inhouseMenu = document.getElementById('inhouse-row-actions-menu');
            if (!inhouseMenu) {
                inhouseMenu = document.createElement('div');
                inhouseMenu.id = 'inhouse-row-actions-menu';
                inhouseMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                inhouseMenu.innerHTML = '<button type="button" id="inhouse-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Edit</button><button type="button" id="inhouse-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Delete</button>';
                document.body.appendChild(inhouseMenu);
            }
            var closeInhouseMenu = function () { inhouseMenu.classList.add('hidden'); activeInhouseRow = null; };
            var openInhouseMenu = function (btn) {
                activeInhouseRow = btn.closest('tr');
                if (!activeInhouseRow) return;
                var rect = btn.getBoundingClientRect();
                inhouseMenu.style.left = Math.max(12, rect.left) + 'px';
                inhouseMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                inhouseMenu.classList.remove('hidden');
            };
            inhouseTableBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.inhouse-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openInhouseMenu(btn);
            });
            inhouseTableBody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.inhouse-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openInhouseMenu(btn); }
            });
            var inhouseEditBtn = document.getElementById('inhouse-row-edit-btn');
            if (inhouseEditBtn) inhouseEditBtn.addEventListener('click', function () {
                if (!activeInhouseRow) return;
                editingInhouseId = parseInt(activeInhouseRow.getAttribute('data-inhouse-id') || '', 10) || null;
                openInHouseAseanModal();
                var eventEl = document.getElementById('inhouse-event-name');
                var participantsEl = document.getElementById('inhouse-participants');
                var yearEl = document.getElementById('inhouse-year');
                if (eventEl) eventEl.value = activeInhouseRow.getAttribute('data-inhouse-event') || '';
                if (participantsEl) participantsEl.value = activeInhouseRow.getAttribute('data-inhouse-participants') || '0';
                if (yearEl) yearEl.value = activeInhouseRow.getAttribute('data-inhouse-year') || '';
                setInhousePartnersFromCsv(activeInhouseRow.getAttribute('data-inhouse-partners') || '');
                setInhouseCountriesFromCsv(activeInhouseRow.getAttribute('data-inhouse-countries') || '');
                var titleEl = document.getElementById('in-house-asean-internationalization-events-modal-title');
                if (titleEl) titleEl.textContent = 'Edit In-house Event';
                var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                if (subtitleEl) subtitleEl.textContent = 'Update this in-house ASEAN event and save changes.';
                var saveBtn = document.getElementById('inhouse-save');
                if (saveBtn) saveBtn.textContent = 'Save Changes';
                closeInhouseMenu();
            });
            var inhouseDeleteBtn = document.getElementById('inhouse-row-delete-btn');
            if (inhouseDeleteBtn) inhouseDeleteBtn.addEventListener('click', async function () {
                if (!activeInhouseRow) return;
                var id = activeInhouseRow.getAttribute('data-inhouse-id');
                if (!id) return;
                if (!window.confirm('Delete this in-house ASEAN event record?')) return;
                try {
                    var resp = await fetch('api/in-house-asean-events.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadInhouseEvents();
                } catch (_) {
                    window.alert('Failed to delete in-house event record.');
                }
                closeInhouseMenu();
            });
            document.addEventListener('click', function () { closeInhouseMenu(); });
            window.addEventListener('scroll', function () { closeInhouseMenu(); }, true);
        }

        var inhouseCloseBtn = document.getElementById('in-house-asean-internationalization-events-modal-close');
        if (inhouseCloseBtn) inhouseCloseBtn.addEventListener('click', closeInHouseAseanModal);
        var inhouseBackdropEl = document.getElementById('in-house-asean-internationalization-events-modal-backdrop');
        if (inhouseBackdropEl) inhouseBackdropEl.addEventListener('click', closeInHouseAseanModal);
        var inhouseCancelBtn = document.getElementById('inhouse-cancel');
        if (inhouseCancelBtn) inhouseCancelBtn.addEventListener('click', closeInHouseAseanModal);
        var inhouseForm = document.getElementById('in-house-asean-internationalization-events-form');
        if (inhouseForm) {
            inhouseForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveInhouseEventToDatabase().then(function () { closeInHouseAseanModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }
        var inhouseSaveBtn = document.getElementById('inhouse-save');
        if (inhouseSaveBtn) {
            inhouseSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveInhouseEventToDatabase().then(function () { closeInHouseAseanModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }

        var iscTableBody = document.getElementById('isc-table-body');

        function getIscPartnersText() {
            var wrap = document.getElementById('isc-partners');
            if (!wrap) return '';
            return Array.prototype.slice.call(wrap.querySelectorAll('.isc-partner-chip .isc-partner-name')).map(function (el) {
                return (el.textContent || '').trim();
            }).filter(function (x) { return x; }).join(', ');
        }

        function getIscCountriesText() {
            var wrap = document.getElementById('isc-countries');
            if (!wrap) return '';
            return Array.prototype.slice.call(wrap.querySelectorAll('.isc-country-chip .isc-country-name')).map(function (el) {
                return (el.textContent || '').trim();
            }).filter(function (x) { return x; }).join(', ');
        }

        function setIscPartnersFromCsv(csv) {
            var wrap = document.getElementById('isc-partners');
            if (!wrap) return;
            Array.prototype.slice.call(wrap.querySelectorAll('.isc-partner-chip')).forEach(function (chip) { chip.remove(); });
            String(csv || '').split(',').map(function (x) { return x.trim(); }).filter(function (x) { return x; }).forEach(function (value) {
                var chip = document.createElement('span');
                chip.className = 'bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded-full flex items-center gap-1 isc-partner-chip';
                chip.innerHTML = '<span class="isc-partner-name">' + escapeHtml(value) + '</span><button type="button" class="isc-partner-remove p-0.5 rounded hover:bg-primary/10" aria-label="Remove partner"><span class="material-symbols-outlined text-xs">close</span></button>';
                wrap.appendChild(chip);
            });
        }

        function setIscCountriesFromCsv(csv) {
            var wrap = document.getElementById('isc-countries');
            if (!wrap) return;
            Array.prototype.slice.call(wrap.querySelectorAll('.isc-country-chip')).forEach(function (chip) { chip.remove(); });
            String(csv || '').split(',').map(function (x) { return x.trim(); }).filter(function (x) { return x; }).forEach(function (value) {
                var chip = document.createElement('span');
                chip.className = 'bg-secondary-container text-on-secondary-fixed-variant text-[10px] font-bold px-2 py-1 rounded-full flex items-center gap-1 isc-country-chip';
                chip.innerHTML = '<span class="isc-country-name">' + escapeHtml(value) + '</span><button type="button" class="isc-country-remove p-0.5 rounded hover:opacity-90" aria-label="Remove country"><span class="material-symbols-outlined text-xs">close</span></button>';
                wrap.appendChild(chip);
            });
        }

        async function saveIscToDatabase() {
            var centerEl = document.getElementById('isc-center-name');
            var yearEl = document.getElementById('isc-year-established');
            var centerName = centerEl ? (centerEl.value || '').trim() : '';
            var yearRaw = yearEl ? (yearEl.value || '').trim() : '';
            var partners = getIscPartnersText();
            var countries = getIscCountriesText();
            var year = yearRaw === '' ? null : parseInt(yearRaw, 10);
            if (!centerName) throw new Error('International Center is required.');
            if (year !== null && (isNaN(year) || year < 1900 || year > 2100)) throw new Error('Please provide a valid year.');

            var formData = new FormData();
            formData.append('center_name', centerName);
            formData.append('partners', partners);
            formData.append('countries', countries);
            formData.append('year_established', year === null ? '' : String(year));

            var endpoint = editingIscId ? ('api/international-sustainability-centers.php?action=update&id=' + encodeURIComponent(String(editingIscId))) : 'api/international-sustainability-centers.php';
            var resp = await fetch(endpoint, { method: 'POST', body: formData, credentials: 'same-origin' });
            var result = await resp.json();
            if (!resp.ok || !result || !result.success) throw new Error((result && result.error) || 'Failed to save record');
            await loadIscRecords();
        }

        function renderIscRecords(rows) {
            if (!iscTableBody) return;
            iscTableBody.innerHTML = '';
            var entries = rows || [];
            if (!entries.length) {
                iscTableBody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-on-surface-variant text-sm">No international/sustainability centers yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td></tr>';
                return;
            }
            entries.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-isc-id', String(r.id || ''));
                tr.setAttribute('data-isc-center', r.center_name || '');
                tr.setAttribute('data-isc-partners', r.partners || '');
                tr.setAttribute('data-isc-countries', r.countries || '');
                tr.setAttribute('data-isc-year', r.year_established || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.center_name || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.partners || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.countries || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.year_established)) + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="isc-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0">more_vert</span></td>';
                iscTableBody.appendChild(tr);
            });
        }

        async function loadIscRecords() {
            if (!iscTableBody) return;
            iscTableBody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/international-sustainability-centers.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderIscRecords(result.data || []);
            } catch (_) {
                renderIscRecords([]);
            }
        }

        if (iscTableBody) {
            var activeIscRow = null;
            var iscMenu = document.getElementById('isc-row-actions-menu');
            if (!iscMenu) {
                iscMenu = document.createElement('div');
                iscMenu.id = 'isc-row-actions-menu';
                iscMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                iscMenu.innerHTML = '<button type="button" id="isc-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Edit</button><button type="button" id="isc-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Delete</button>';
                document.body.appendChild(iscMenu);
            }
            var closeIscMenu = function () { iscMenu.classList.add('hidden'); activeIscRow = null; };
            var openIscMenu = function (btn) {
                activeIscRow = btn.closest('tr');
                if (!activeIscRow) return;
                var rect = btn.getBoundingClientRect();
                iscMenu.style.left = Math.max(12, rect.left) + 'px';
                iscMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                iscMenu.classList.remove('hidden');
            };
            iscTableBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.isc-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openIscMenu(btn);
            });
            iscTableBody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.isc-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openIscMenu(btn); }
            });
            var iscEditBtn = document.getElementById('isc-row-edit-btn');
            if (iscEditBtn) iscEditBtn.addEventListener('click', function () {
                if (!activeIscRow) return;
                editingIscId = parseInt(activeIscRow.getAttribute('data-isc-id') || '', 10) || null;
                openInternationalSustainabilityCentersModal();
                var centerEl = document.getElementById('isc-center-name');
                var yearEl = document.getElementById('isc-year-established');
                if (centerEl) centerEl.value = activeIscRow.getAttribute('data-isc-center') || '';
                if (yearEl) yearEl.value = activeIscRow.getAttribute('data-isc-year') || '';
                setIscPartnersFromCsv(activeIscRow.getAttribute('data-isc-partners') || '');
                setIscCountriesFromCsv(activeIscRow.getAttribute('data-isc-countries') || '');
                var titleEl = document.getElementById('international-sustainability-centers-modal-title');
                if (titleEl) titleEl.textContent = 'Edit Sustainability Center';
                var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                if (subtitleEl) subtitleEl.textContent = 'Update this center record and save changes.';
                var saveBtn = document.getElementById('isc-save');
                if (saveBtn) saveBtn.textContent = 'Save Changes';
                closeIscMenu();
            });
            var iscDeleteBtn = document.getElementById('isc-row-delete-btn');
            if (iscDeleteBtn) iscDeleteBtn.addEventListener('click', async function () {
                if (!activeIscRow) return;
                var id = activeIscRow.getAttribute('data-isc-id');
                if (!id) return;
                if (!window.confirm('Delete this international/sustainability center record?')) return;
                try {
                    var resp = await fetch('api/international-sustainability-centers.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadIscRecords();
                } catch (_) {
                    window.alert('Failed to delete center record.');
                }
                closeIscMenu();
            });
            document.addEventListener('click', function () { closeIscMenu(); });
            window.addEventListener('scroll', function () { closeIscMenu(); }, true);
        }

        var centersCloseBtn = document.getElementById('international-sustainability-centers-modal-close');
        if (centersCloseBtn) centersCloseBtn.addEventListener('click', closeInternationalSustainabilityCentersModal);
        var centersBackdropEl = document.getElementById('international-sustainability-centers-modal-backdrop');
        if (centersBackdropEl) centersBackdropEl.addEventListener('click', closeInternationalSustainabilityCentersModal);
        var centersCancelBtn = document.getElementById('isc-cancel');
        if (centersCancelBtn) centersCancelBtn.addEventListener('click', closeInternationalSustainabilityCentersModal);
        var centersForm = document.getElementById('international-sustainability-centers-form');
        if (centersForm) {
            centersForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveIscToDatabase().then(function () { closeInternationalSustainabilityCentersModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }
        var iscSaveBtn = document.getElementById('isc-save');
        if (iscSaveBtn) {
            iscSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveIscToDatabase().then(function () { closeInternationalSustainabilityCentersModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }

        var studyphCloseBtn = document.getElementById('studyph-program-modal-close');
        if (studyphCloseBtn) studyphCloseBtn.addEventListener('click', closeStudyPHProgramModal);
        var studyphBackdropEl = document.getElementById('studyph-program-modal-backdrop');
        if (studyphBackdropEl) studyphBackdropEl.addEventListener('click', closeStudyPHProgramModal);
        var studyphCancelBtn = document.getElementById('studyph-cancel');
        if (studyphCancelBtn) studyphCancelBtn.addEventListener('click', closeStudyPHProgramModal);
        var studyphTableBody = document.getElementById('studyph-table-body');

        function formatStudyphAmount(v) {
            var n = Number(v);
            if (!isFinite(n)) return '—';
            return 'PHP ' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function getStudyphSelectedSdgs() {
            var sdgInput = document.getElementById('studyph-sdgs-value');
            return sdgInput ? (sdgInput.value || '').trim() : '';
        }

        async function saveStudyphToDatabase() {
            var year = (document.getElementById('studyph-year') || {}).value || '';
            var kra = ((document.getElementById('studyph-kra') || {}).value || '').trim();
            var title = ((document.getElementById('studyph-project-title') || {}).value || '').trim();
            var field = ((document.getElementById('studyph-field-area') || {}).value || '').trim();
            var sdg = getStudyphSelectedSdgs();
            var description = ((document.getElementById('studyph-description') || {}).value || '').trim();
            var amountRaw = ((document.getElementById('studyph-amount') || {}).value || '').trim();
            var benQtyRaw = ((document.getElementById('studyph-beneficiaries-qty') || {}).value || '').trim();
            var benType = ((document.getElementById('studyph-beneficiaries-type') || {}).value || '').trim();
            var kpi = ((document.getElementById('studyph-kpi') || {}).value || '').trim();
            var kpiValue = ((document.getElementById('studyph-kpi-value') || {}).value || '').trim();
            var amount = amountRaw === '' ? null : Number(amountRaw);
            var benQty = benQtyRaw === '' ? null : parseInt(benQtyRaw, 10);

            if (!year) throw new Error('Year is required.');
            if (!title) throw new Error('Project title is required.');
            if (amount !== null && (!isFinite(amount) || amount < 0)) throw new Error('Amount must be a valid positive number.');
            if (benQty !== null && (!isFinite(benQty) || benQty < 0)) throw new Error('Beneficiaries quantity must be valid.');

            var fd = new FormData();
            fd.append('year', year);
            fd.append('kra', kra);
            fd.append('project_title', title);
            fd.append('field_area', field);
            fd.append('sdg_covered', sdg);
            fd.append('description', description);
            fd.append('amount', amount === null ? '' : String(amount));
            fd.append('beneficiaries_qty', benQty === null ? '' : String(benQty));
            fd.append('beneficiaries_type', benType);
            fd.append('kpi', kpi);
            fd.append('kpi_value', kpiValue);

            var endpoint = editingStudyphId ? ('api/studyph-program.php?action=update&id=' + encodeURIComponent(String(editingStudyphId))) : 'api/studyph-program.php';
            var resp = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' });
            var result = await resp.json();
            if (!resp.ok || !result || !result.success) throw new Error((result && result.error) || 'Failed to save record');
            await loadStudyphRecords();
        }

        function renderStudyphRecords(rows) {
            if (!studyphTableBody) return;
            studyphTableBody.innerHTML = '';
            var entries = rows || [];
            if (!entries.length) {
                studyphTableBody.innerHTML = '<tr><td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">No StudyPH records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td></tr>';
                return;
            }
            entries.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors';
                tr.setAttribute('data-studyph-id', String(r.id || ''));
                tr.setAttribute('data-studyph-year', r.year || '');
                tr.setAttribute('data-studyph-kra', r.kra || '');
                tr.setAttribute('data-studyph-title', r.project_title || '');
                tr.setAttribute('data-studyph-field', r.field_area || '');
                tr.setAttribute('data-studyph-sdg', r.sdg_covered || '');
                tr.setAttribute('data-studyph-description', r.description || '');
                tr.setAttribute('data-studyph-amount', r.amount || '');
                tr.setAttribute('data-studyph-benqty', r.beneficiaries_qty || '');
                tr.setAttribute('data-studyph-bentype', r.beneficiaries_type || '');
                tr.setAttribute('data-studyph-kpi', r.kpi || '');
                tr.setAttribute('data-studyph-kpivalue', r.kpi_value || '');
                tr.innerHTML =
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudentMobilityYear(r.year)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.kra || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface font-medium text-xs">' + escapeHtml(r.project_title || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.field_area || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.sdg_covered || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(formatStudyphAmount(r.amount)) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml((r.beneficiaries_qty ? String(r.beneficiaries_qty) : '—') + (r.beneficiaries_type ? (' (' + r.beneficiaries_type + ')') : '')) + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.kpi || '—') + '</td>' +
                    '<td class="py-3 px-4 text-on-surface-variant text-xs">' + escapeHtml(r.kpi_value || '—') + '</td>' +
                    '<td class="py-3 px-4 text-right"><span class="studyph-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0">more_vert</span></td>';
                studyphTableBody.appendChild(tr);
            });
        }

        async function loadStudyphRecords() {
            if (!studyphTableBody) return;
            studyphTableBody.innerHTML = '<tr><td colspan="10" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            try {
                var resp = await fetch('api/studyph-program.php?action=list&ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) throw new Error();
                renderStudyphRecords(result.data || []);
            } catch (_) {
                renderStudyphRecords([]);
            }
        }

        if (studyphTableBody) {
            var activeStudyphRow = null;
            var studyphMenu = document.getElementById('studyph-row-actions-menu');
            if (!studyphMenu) {
                studyphMenu = document.createElement('div');
                studyphMenu.id = 'studyph-row-actions-menu';
                studyphMenu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                studyphMenu.innerHTML = '<button type="button" id="studyph-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Edit</button><button type="button" id="studyph-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container">Delete</button>';
                document.body.appendChild(studyphMenu);
            }
            var closeStudyphMenu = function () { studyphMenu.classList.add('hidden'); activeStudyphRow = null; };
            var openStudyphMenu = function (btn) {
                activeStudyphRow = btn.closest('tr');
                if (!activeStudyphRow) return;
                var rect = btn.getBoundingClientRect();
                studyphMenu.style.left = Math.max(12, rect.left) + 'px';
                studyphMenu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                studyphMenu.classList.remove('hidden');
            };
            studyphTableBody.addEventListener('click', function (e) {
                var btn = e.target.closest('.studyph-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openStudyphMenu(btn);
            });
            studyphTableBody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.studyph-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openStudyphMenu(btn); }
            });
            var studyphEditBtn = document.getElementById('studyph-row-edit-btn');
            if (studyphEditBtn) studyphEditBtn.addEventListener('click', function () {
                if (!activeStudyphRow) return;
                editingStudyphId = parseInt(activeStudyphRow.getAttribute('data-studyph-id') || '', 10) || null;
                openStudyPHProgramModal();
                var setVal = function (id, key) { var el = document.getElementById(id); if (el) el.value = activeStudyphRow.getAttribute(key) || ''; };
                setVal('studyph-year', 'data-studyph-year');
                setVal('studyph-kra', 'data-studyph-kra');
                setVal('studyph-project-title', 'data-studyph-title');
                setVal('studyph-field-area', 'data-studyph-field');
                setVal('studyph-description', 'data-studyph-description');
                setVal('studyph-amount', 'data-studyph-amount');
                setVal('studyph-beneficiaries-qty', 'data-studyph-benqty');
                setVal('studyph-beneficiaries-type', 'data-studyph-bentype');
                setVal('studyph-kpi', 'data-studyph-kpi');
                setVal('studyph-kpi-value', 'data-studyph-kpivalue');
                var sdgVal = activeStudyphRow.getAttribute('data-studyph-sdg') || '';
                var sdgInput = document.getElementById('studyph-sdgs-value');
                if (sdgInput) sdgInput.value = sdgVal;
                var titleEl = document.getElementById('studyph-program-modal-title');
                if (titleEl) titleEl.textContent = 'Edit StudyPH Program Entry';
                var subtitleEl = titleEl ? titleEl.nextElementSibling : null;
                if (subtitleEl) subtitleEl.textContent = 'Update this StudyPH record and save changes.';
                var saveBtn = document.getElementById('studyph-save');
                if (saveBtn) saveBtn.textContent = 'Save Changes';
                closeStudyphMenu();
            });
            var studyphDeleteBtn = document.getElementById('studyph-row-delete-btn');
            if (studyphDeleteBtn) studyphDeleteBtn.addEventListener('click', async function () {
                if (!activeStudyphRow) return;
                var id = activeStudyphRow.getAttribute('data-studyph-id');
                if (!id) return;
                if (!window.confirm('Delete this StudyPH record?')) return;
                try {
                    var resp = await fetch('api/studyph-program.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' });
                    var result = await resp.json();
                    if (!resp.ok || !result || !result.success) throw new Error();
                    await loadStudyphRecords();
                } catch (_) {
                    window.alert('Failed to delete StudyPH record.');
                }
                closeStudyphMenu();
            });
            document.addEventListener('click', function () { closeStudyphMenu(); });
            window.addEventListener('scroll', function () { closeStudyphMenu(); }, true);
        }

        var studyphForm = document.getElementById('studyph-program-form');
        if (studyphForm) {
            studyphForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveStudyphToDatabase().then(function () { closeStudyPHProgramModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }
        var studyphSaveBtn = document.getElementById('studyph-save');
        if (studyphSaveBtn) {
            studyphSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveStudyphToDatabase().then(function () { closeStudyPHProgramModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }

        var coilCloseBtn = document.getElementById('coil-entry-modal-close');
        if (coilCloseBtn) coilCloseBtn.addEventListener('click', closeCoilModal);
        var coilBackdropEl = document.getElementById('coil-entry-modal-backdrop');
        if (coilBackdropEl) coilBackdropEl.addEventListener('click', closeCoilModal);
        var coilCancelBtn = document.getElementById('coil-entry-cancel');
        if (coilCancelBtn) coilCancelBtn.addEventListener('click', closeCoilModal);
        var coilForm = document.getElementById('coil-entry-form');
        if (coilForm) {
            coilForm.addEventListener('submit', function (e) {
                e.preventDefault();
                saveCoilToDatabase().then(function () { closeCoilModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }
        var coilSaveBtn = document.getElementById('coil-entry-save');
        if (coilSaveBtn) {
            coilSaveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveCoilToDatabase().then(function () { closeCoilModal(); }).catch(function (err) { window.alert(err.message || 'Failed to save record.'); });
            });
        }

        document.querySelectorAll('.lp-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var t = btn.getAttribute('data-lp-tab');
                document.querySelectorAll('.lp-tab-btn').forEach(function (b) {
                    var isActive = b === btn;
                    b.classList.toggle('bg-primary', isActive);
                    b.classList.toggle('text-on-primary', isActive);
                    b.classList.toggle('shadow-sm', isActive);
                    b.classList.toggle('bg-surface-container-high', !isActive);
                    b.classList.toggle('text-on-surface-variant', !isActive);
                    b.classList.toggle('hover:bg-surface-container', !isActive);
                });
                var panels = {
                    'international': 'lp-panel-international',
                    'local-industry': 'lp-panel-local-industry',
                    'local-academe': 'lp-panel-local-academe'
                };
                Object.keys(panels).forEach(function (key) {
                    var panel = document.getElementById(panels[key]);
                    if (panel) panel.classList.toggle('hidden', key !== t);
                });
            });
        });

        var fab = document.getElementById('mobility-fab-add');
        if (fab) fab.addEventListener('click', function () {
            var mem = document.getElementById('panel-memberships');
            var link = document.getElementById('panel-linkages');
            var student = document.getElementById('panel-student-mobility');
            var sch = document.getElementById('panel-scholarships');
            var staff = document.getElementById('panel-staff-mobility');
            var fullTime = document.getElementById('panel-full-time-foreign-students');
            var fullTimeFaculty = document.getElementById('panel-full-time-foreign-faculty');
            var research = document.getElementById('panel-internationalization-research');
            var tn = document.getElementById('panel-transnational-education-program');
            var collab = document.getElementById('panel-collaborative-events-activities');
            var inhouse = document.getElementById('panel-in-house-asean-internationalization-events');
            var centers = document.getElementById('panel-international-sustainability-centers');
            var studyph = document.getElementById('panel-studyph-program');
            var coil = document.getElementById('panel-coil-classes');
            if (mem && !mem.classList.contains('hidden')) openMembershipModal();
            else if (link && !link.classList.contains('hidden')) openLinkagesModal();
            else if (student && !student.classList.contains('hidden')) openStudentMobilityModal();
            else if (sch && !sch.classList.contains('hidden')) openScholarshipsModal();
            else if (staff && !staff.classList.contains('hidden')) openStaffMobilityModal();
            else if (fullTime && !fullTime.classList.contains('hidden')) openFullTimeForeignStudentsModal();
            else if (fullTimeFaculty && !fullTimeFaculty.classList.contains('hidden')) openFullTimeForeignFacultyModal();
            else if (tn && !tn.classList.contains('hidden')) openTransnationalEducationProgramModal();
            else if (collab && !collab.classList.contains('hidden')) openCollaborativeEventsActivitiesModal();
            else if (inhouse && !inhouse.classList.contains('hidden')) openInHouseAseanModal();
            else if (centers && !centers.classList.contains('hidden')) openInternationalSustainabilityCentersModal();
            else if (studyph && !studyph.classList.contains('hidden')) openStudyPHProgramModal();
            else if (coil && !coil.classList.contains('hidden')) openCoilModal();
            else if (research && !research.classList.contains('hidden')) openInternationalizationResearchModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var link = document.getElementById('linkages-entry-modal');
                if (link && !link.classList.contains('hidden')) closeLinkagesModal();
                var student = document.getElementById('student-mobility-entry-modal');
                if (student && !student.classList.contains('hidden')) closeStudentMobilityModal();
                var sch = document.getElementById('scholarships-entry-modal');
                if (sch && !sch.classList.contains('hidden')) closeScholarshipsModal();
                var staff = document.getElementById('staff-mobility-entry-modal');
                if (staff && !staff.classList.contains('hidden')) closeStaffMobilityModal();
                var fullTime = document.getElementById('full-time-foreign-students-modal');
                if (fullTime && !fullTime.classList.contains('hidden')) closeFullTimeForeignStudentsModal();
                var fullTimeDetails = document.getElementById('full-time-students-detail-modal');
                if (fullTimeDetails && !fullTimeDetails.classList.contains('hidden')) closeFullTimeStudentsDetailModal();
                var fullTimeFaculty = document.getElementById('full-time-foreign-faculty-modal');
                if (fullTimeFaculty && !fullTimeFaculty.classList.contains('hidden')) closeFullTimeForeignFacultyModal();
                var fullTimeFacultyDetails = document.getElementById('full-time-faculty-detail-modal');
                if (fullTimeFacultyDetails && !fullTimeFacultyDetails.classList.contains('hidden')) closeFullTimeFacultyDetailModal();
                var research = document.getElementById('internationalization-research-modal');
                if (research && !research.classList.contains('hidden')) closeInternationalizationResearchModal();
                var tn = document.getElementById('transnational-education-program-modal');
                if (tn && !tn.classList.contains('hidden')) closeTransnationalEducationProgramModal();
                var collab = document.getElementById('collaborative-events-activities-modal');
                if (collab && !collab.classList.contains('hidden')) closeCollaborativeEventsActivitiesModal();
                var inhouse = document.getElementById('in-house-asean-internationalization-events-modal');
                if (inhouse && !inhouse.classList.contains('hidden')) closeInHouseAseanModal();
                var centers = document.getElementById('international-sustainability-centers-modal');
                if (centers && !centers.classList.contains('hidden')) closeInternationalSustainabilityCentersModal();
                var studyph = document.getElementById('studyph-program-modal');
                if (studyph && !studyph.classList.contains('hidden')) closeStudyPHProgramModal();
                var coil = document.getElementById('coil-entry-modal');
                if (coil && !coil.classList.contains('hidden')) closeCoilModal();
                var m = document.getElementById('membership-modal');
                if (m && !m.classList.contains('hidden')) closeMembershipModal();
                var awardsAdd = document.getElementById('awards-add-modal');
                if (awardsAdd && !awardsAdd.classList.contains('hidden')) closeAwardsAddModal();
                var awardsDetail = document.getElementById('awards-detail-modal');
                if (awardsDetail && !awardsDetail.classList.contains('hidden')) closeAwardsDetailModal();
            }
        });

        // Transnational modal: quick add/remove nationality chips
        var tnNationInput = document.getElementById('tn-nationality-input');
        var tnNationContainer = document.getElementById('tn-nationalities');
        if (tnNationInput && tnNationContainer) {
            tnNationInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                var value = (tnNationInput.value || '').trim();
                if (!value) return;

                // Prevent duplicates (case-insensitive, lightweight check).
                var lower = value.toLowerCase();
                var existing = Array.prototype.slice.call(tnNationContainer.querySelectorAll('.tn-nationality-chip')).some(function (chip) {
                    return chip.textContent.trim().toLowerCase().indexOf(lower) !== -1;
                });
                if (existing) {
                    tnNationInput.value = '';
                    return;
                }

                var chip = document.createElement('span');
                chip.className = 'bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded flex items-center gap-1 tn-nationality-chip';
                chip.innerHTML = '<span>' + value + '</span>' +
                    '<button type="button" class="tn-chip-remove p-0.5 rounded hover:bg-primary/10" aria-label="Remove nationality">' +
                    '<span class="material-symbols-outlined text-[12px] text-primary">close</span>' +
                    '</button>';

                tnNationContainer.insertBefore(chip, tnNationInput);
                tnNationInput.value = '';
            });

            tnNationContainer.addEventListener('click', function (e) {
                var target = e.target;
                if (!target) return;
                var removeBtn = target.closest('.tn-chip-remove');
                if (!removeBtn) return;
                var chip = removeBtn.closest('.tn-nationality-chip');
                if (chip) chip.remove();
            });
        }

        // Collaborative events modal: partners chips + participant +/- buttons
        var ceaPartnerInput = document.getElementById('cea-partner-input');
        var ceaPartnerAddBtn = document.getElementById('cea-partner-add');
        var ceaPartnersContainer = document.getElementById('cea-partners');
        if (ceaPartnerInput && ceaPartnersContainer) {
            var addPartnerChip = function () {
                var value = (ceaPartnerInput.value || '').trim();
                if (!value) return;

                var lower = value.toLowerCase();
                var existing = Array.prototype.slice.call(ceaPartnersContainer.querySelectorAll('.cea-partner-chip .cea-partner-name')).some(function (nameEl) {
                    return nameEl.textContent.trim().toLowerCase() === lower;
                });
                if (existing) {
                    ceaPartnerInput.value = '';
                    return;
                }

                var chip = document.createElement('span');
                chip.className = 'bg-surface-container text-[11px] font-bold text-on-surface px-3 py-1 rounded-full flex items-center gap-2 cea-partner-chip';
                chip.innerHTML = '<span class="cea-partner-name">' + value + '</span>' +
                    '<button type="button" class="cea-partner-remove p-0.5 rounded hover:bg-surface-container-high" aria-label="Remove partner">' +
                    '<span class="material-symbols-outlined text-[14px] text-on-surface">close</span>' +
                    '</button>';

                ceaPartnersContainer.appendChild(chip);
                ceaPartnerInput.value = '';
            };

            ceaPartnerInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                addPartnerChip();
            });

            if (ceaPartnerAddBtn) ceaPartnerAddBtn.addEventListener('click', addPartnerChip);

            ceaPartnersContainer.addEventListener('click', function (e) {
                var target = e.target;
                if (!target) return;
                var removeBtn = target.closest('.cea-partner-remove');
                if (removeBtn) {
                    var chip = removeBtn.closest('.cea-partner-chip');
                    if (chip) chip.remove();
                }
            });
        }

        var ceaParticipantsInput = document.getElementById('cea-participants');
        var ceaMinusBtn = document.getElementById('cea-participants-minus');
        var ceaPlusBtn = document.getElementById('cea-participants-plus');
        if (ceaParticipantsInput && ceaMinusBtn && ceaPlusBtn) {
            var setCEAParticipants = function (delta) {
                var v = parseInt(ceaParticipantsInput.value, 10);
                if (isNaN(v)) v = 0;
                v += delta;
                if (v < 0) v = 0;
                ceaParticipantsInput.value = v;
            };
            ceaMinusBtn.addEventListener('click', function () { setCEAParticipants(-1); });
            ceaPlusBtn.addEventListener('click', function () { setCEAParticipants(1); });
        }

        // In-house ASEAN modal: partner chips + country chips + participant +/- buttons
        var inhousePartnerInput = document.getElementById('inhouse-partner-input');
        var inhousePartnerAddBtn = document.getElementById('inhouse-partner-add');
        var inhousePartnersContainer = document.getElementById('inhouse-partners');
        if (inhousePartnerInput && inhousePartnersContainer) {
            var addInhousePartnerChip = function () {
                var value = (inhousePartnerInput.value || '').trim();
                if (!value) return;

                var lower = value.toLowerCase();
                var existing = Array.prototype.slice.call(inhousePartnersContainer.querySelectorAll('.inhouse-partner-chip .inhouse-partner-name')).some(function (nameEl) {
                    return nameEl.textContent.trim().toLowerCase() === lower;
                });
                if (existing) {
                    inhousePartnerInput.value = '';
                    return;
                }

                var chip = document.createElement('span');
                chip.className = 'bg-primary-container text-on-primary-container text-[11px] font-bold px-3 py-1 rounded-full flex items-center gap-2 inhouse-partner-chip';
                chip.innerHTML = '<span class="inhouse-partner-name">' + value + '</span>' +
                    '<button type="button" class="inhouse-partner-remove p-0.5 rounded hover:opacity-90" aria-label="Remove partner">' +
                    '<span class="material-symbols-outlined text-[14px] text-on-primary-container">close</span>' +
                    '</button>';

                inhousePartnersContainer.appendChild(chip);
                inhousePartnerInput.value = '';
            };

            inhousePartnerInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                addInhousePartnerChip();
            });
            if (inhousePartnerAddBtn) inhousePartnerAddBtn.addEventListener('click', addInhousePartnerChip);

            inhousePartnersContainer.addEventListener('click', function (e) {
                var target = e.target;
                if (!target) return;
                var removeBtn = target.closest('.inhouse-partner-remove');
                if (!removeBtn) return;
                var chip = removeBtn.closest('.inhouse-partner-chip');
                if (chip) chip.remove();
            });
        }

        var inhouseCountrySelect = document.getElementById('inhouse-country-select');
        var inhouseCountryAddBtn = document.getElementById('inhouse-country-add');
        var inhouseCountriesContainer = document.getElementById('inhouse-countries');
        if (inhouseCountrySelect && inhouseCountryAddBtn && inhouseCountriesContainer) {
            var addInhouseCountryChip = function () {
                var value = inhouseCountrySelect.value;
                if (!value) return;

                var lower = (value || '').toLowerCase();
                var existing = Array.prototype.slice.call(inhouseCountriesContainer.querySelectorAll('.inhouse-country-chip .inhouse-country-name')).some(function (nameEl) {
                    return nameEl.textContent.trim().toLowerCase() === lower;
                });
                if (existing) return;

                var chip = document.createElement('span');
                chip.className = 'bg-secondary-container text-on-secondary-container text-[11px] font-bold px-3 py-1 rounded-full flex items-center gap-2 inhouse-country-chip';
                chip.innerHTML = '<span class="inhouse-country-name">' + value + '</span>' +
                    '<button type="button" class="inhouse-country-remove p-0.5 rounded hover:opacity-90" aria-label="Remove country">' +
                    '<span class="material-symbols-outlined text-[14px] text-on-secondary-container">close</span>' +
                    '</button>';

                inhouseCountriesContainer.appendChild(chip);
                inhouseCountrySelect.value = '';
            };

            inhouseCountryAddBtn.addEventListener('click', addInhouseCountryChip);

            inhouseCountriesContainer.addEventListener('click', function (e) {
                var target = e.target;
                if (!target) return;
                var removeBtn = target.closest('.inhouse-country-remove');
                if (!removeBtn) return;
                var chip = removeBtn.closest('.inhouse-country-chip');
                if (chip) chip.remove();
            });
        }

        var inhouseParticipantsInput = document.getElementById('inhouse-participants');
        var inhouseMinusBtn = document.getElementById('inhouse-participants-minus');
        var inhousePlusBtn = document.getElementById('inhouse-participants-plus');
        if (inhouseParticipantsInput && inhouseMinusBtn && inhousePlusBtn) {
            var setInhouseParticipants = function (delta) {
                var v = parseInt(inhouseParticipantsInput.value, 10);
                if (isNaN(v)) v = 0;
                v += delta;
                if (v < 0) v = 0;
                inhouseParticipantsInput.value = v;
            };
            inhouseMinusBtn.addEventListener('click', function () { setInhouseParticipants(-1); });
            inhousePlusBtn.addEventListener('click', function () { setInhouseParticipants(1); });
        }

        // International/Sustainability Centers modal: partner chips + country chips
        var iscPartnerInput = document.getElementById('isc-partner-input');
        var iscPartnerAddBtn = document.getElementById('isc-partner-add');
        var iscPartnersContainer = document.getElementById('isc-partners');
        if (iscPartnerInput && iscPartnersContainer) {
            var addISCPPartnerChip = function () {
                var value = (iscPartnerInput.value || '').trim();
                if (!value) return;

                var lower = value.toLowerCase();
                var existing = Array.prototype.slice.call(iscPartnersContainer.querySelectorAll('.isc-partner-chip .isc-partner-name')).some(function (nameEl) {
                    return nameEl.textContent.trim().toLowerCase() === lower;
                });
                if (existing) {
                    iscPartnerInput.value = '';
                    return;
                }

                var chip = document.createElement('span');
                chip.className = 'bg-primary/10 text-primary text-[10px] font-bold px-2 py-1 rounded-full flex items-center gap-1 isc-partner-chip';
                chip.innerHTML = '<span class="isc-partner-name">' + value + '</span>' +
                    '<button type="button" class="isc-partner-remove p-0.5 rounded hover:bg-primary/10" aria-label="Remove partner">' +
                    '<span class="material-symbols-outlined text-[12px] text-primary">close</span>' +
                    '</button>';

                iscPartnersContainer.appendChild(chip);
                iscPartnerInput.value = '';
            };

            iscPartnerInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                addISCPPartnerChip();
            });
            if (iscPartnerAddBtn) iscPartnerAddBtn.addEventListener('click', addISCPPartnerChip);

            iscPartnersContainer.addEventListener('click', function (e) {
                var target = e.target;
                if (!target) return;
                var removeBtn = target.closest('.isc-partner-remove');
                if (!removeBtn) return;
                var chip = removeBtn.closest('.isc-partner-chip');
                if (chip) chip.remove();
            });
        }

        var iscCountryInput = document.getElementById('isc-country-input');
        var iscCountriesContainer = document.getElementById('isc-countries');
        if (iscCountryInput && iscCountriesContainer) {
            var addISCCountryChip = function () {
                var value = (iscCountryInput.value || '').trim();
                if (!value) return;

                var lower = value.toLowerCase();
                var existing = Array.prototype.slice.call(iscCountriesContainer.querySelectorAll('.isc-country-chip .isc-country-name')).some(function (nameEl) {
                    return nameEl.textContent.trim().toLowerCase() === lower;
                });
                if (existing) {
                    iscCountryInput.value = '';
                    return;
                }

                var chip = document.createElement('span');
                chip.className = 'bg-secondary-container text-on-secondary-fixed-variant text-[10px] font-bold px-2 py-1 rounded-full flex items-center gap-1 isc-country-chip';
                chip.innerHTML = '<span class="isc-country-name">' + value + '</span>' +
                    '<button type="button" class="isc-country-remove p-0.5 rounded hover:opacity-90" aria-label="Remove country">' +
                    '<span class="material-symbols-outlined text-[12px] text-on-surface">close</span>' +
                    '</button>';

                iscCountriesContainer.appendChild(chip);
                iscCountryInput.value = '';
            };

            iscCountryInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                addISCCountryChip();
            });

            iscCountriesContainer.addEventListener('click', function (e) {
                var target = e.target;
                if (!target) return;
                var removeBtn = target.closest('.isc-country-remove');
                if (!removeBtn) return;
                var chip = removeBtn.closest('.isc-country-chip');
                if (chip) chip.remove();
            });
        }

        // StudyPH modal: SDG pill toggles
        var studyphSdgPills = document.getElementById('studyph-sdg-pills');
        var studyphSdgValue = document.getElementById('studyph-sdgs-value');
        if (studyphSdgPills && studyphSdgValue) {
            var pills = studyphSdgPills.querySelectorAll('.studyph-sdg-pill');

            var syncHiddenValue = function () {
                var selected = Array.prototype.slice.call(pills).filter(function (b) {
                    return b.classList.contains('bg-primary');
                }).map(function (b) {
                    return b.getAttribute('data-value');
                }).filter(Boolean);
                studyphSdgValue.value = selected.join(', ');
            };

            var setPillSelected = function (btn, selected) {
                btn.classList.toggle('bg-primary', selected);
                btn.classList.toggle('text-on-primary', selected);
                btn.classList.toggle('border-primary/20', selected);

                // Default/unselected look
                btn.classList.toggle('bg-transparent', !selected);
                btn.classList.toggle('text-on-surface-variant', !selected);
                btn.classList.toggle('border-outline-variant/30', !selected);

                // Remove conflicting classes
                btn.classList.remove('text-primary');
            };

            pills.forEach(function (btn) {
                setPillSelected(btn, false);
                btn.addEventListener('click', function () {
                    var isSelected = btn.classList.contains('bg-primary');
                    setPillSelected(btn, !isSelected);
                    syncHiddenValue();
                });
            });

            syncHiddenValue();
        }

        var inboundScopeBtn = document.getElementById('student-scope-inbound');
        if (inboundScopeBtn) {
            inboundScopeBtn.addEventListener('click', function () {
                updateStudentScopeUI(true);
                var v = document.getElementById('student-scope-value');
                if (v) v.value = 'inbound';
            });
        }
        var outboundScopeBtn = document.getElementById('student-scope-outbound');
        if (outboundScopeBtn) {
            outboundScopeBtn.addEventListener('click', function () {
                updateStudentScopeUI(false);
                var v = document.getElementById('student-scope-value');
                if (v) v.value = 'outbound';
            });
        }

        var shortDurationBtn = document.getElementById('student-duration-short');
        if (shortDurationBtn) {
            shortDurationBtn.addEventListener('click', function () {
                updateStudentDurationUI('short');
            });
        }
        var longDurationBtn = document.getElementById('student-duration-long');
        if (longDurationBtn) {
            longDurationBtn.addEventListener('click', function () {
                updateStudentDurationUI('long');
            });
        }

        var scholarshipInboundBtn = document.getElementById('scholarship-scope-inbound');
        if (scholarshipInboundBtn) scholarshipInboundBtn.addEventListener('click', function () { updateScholarshipScopeUI(true); });
        var scholarshipOutboundBtn = document.getElementById('scholarship-scope-outbound');
        if (scholarshipOutboundBtn) scholarshipOutboundBtn.addEventListener('click', function () { updateScholarshipScopeUI(false); });

        var modOnsite = document.getElementById('scholarship-modality-onsite');
        if (modOnsite) modOnsite.addEventListener('click', function () { updateScholarshipModalityUI('on-site'); });
        var modVirtual = document.getElementById('scholarship-modality-virtual');
        if (modVirtual) modVirtual.addEventListener('click', function () { updateScholarshipModalityUI('virtual'); });
        var modHybrid = document.getElementById('scholarship-modality-hybrid');
        if (modHybrid) modHybrid.addEventListener('click', function () { updateScholarshipModalityUI('hybrid'); });

        var staffInboundBtn = document.getElementById('staff-scope-inbound');
        if (staffInboundBtn) staffInboundBtn.addEventListener('click', function () { updateStaffScopeUI(true); });
        var staffOutboundBtn = document.getElementById('staff-scope-outbound');
        if (staffOutboundBtn) staffOutboundBtn.addEventListener('click', function () { updateStaffScopeUI(false); });

        var staffModPhysical = document.getElementById('staff-modality-physical');
        if (staffModPhysical) staffModPhysical.addEventListener('click', function () { updateStaffModalityUI('physical'); });
        var staffModVirtual = document.getElementById('staff-modality-virtual');
        if (staffModVirtual) staffModVirtual.addEventListener('click', function () { updateStaffModalityUI('virtual'); });
        var staffModHybrid = document.getElementById('staff-modality-hybrid');
        if (staffModHybrid) staffModHybrid.addEventListener('click', function () { updateStaffModalityUI('hybrid'); });

        var fullTimeModOnsite = document.getElementById('full-time-modality-onsite');
        if (fullTimeModOnsite) fullTimeModOnsite.addEventListener('click', function () { updateFullTimeModalityUI('on-site'); });
        var fullTimeModVirtual = document.getElementById('full-time-modality-virtual');
        if (fullTimeModVirtual) fullTimeModVirtual.addEventListener('click', function () { updateFullTimeModalityUI('virtual'); });
        var fullTimeModHybrid = document.getElementById('full-time-modality-hybrid');
        if (fullTimeModHybrid) fullTimeModHybrid.addEventListener('click', function () { updateFullTimeModalityUI('hybrid'); });

        var fullTimeUnderBtn = document.getElementById('full-time-level-undergraduate');
        if (fullTimeUnderBtn) fullTimeUnderBtn.addEventListener('click', function () { updateFullTimeLevelUI('undergraduate'); });
        var fullTimeGradBtn = document.getElementById('full-time-level-graduate');
        if (fullTimeGradBtn) fullTimeGradBtn.addEventListener('click', function () { updateFullTimeLevelUI('graduate'); });

        var fullTimeFacultyInboundBtn = document.getElementById('full-time-faculty-scope-inbound');
        if (fullTimeFacultyInboundBtn) fullTimeFacultyInboundBtn.addEventListener('click', function () { updateFullTimeFacultyScopeUI('inbound'); });
        var fullTimeFacultyOutboundBtn = document.getElementById('full-time-faculty-scope-outbound');
        if (fullTimeFacultyOutboundBtn) fullTimeFacultyOutboundBtn.addEventListener('click', function () { updateFullTimeFacultyScopeUI('outbound'); });
        var fullTimeFacultyUnderBtn = document.getElementById('full-time-faculty-level-undergraduate');
        if (fullTimeFacultyUnderBtn) fullTimeFacultyUnderBtn.addEventListener('click', function () { updateFullTimeFacultyLevelUI('undergraduate'); });
        var fullTimeFacultyGradBtn = document.getElementById('full-time-faculty-level-graduate');
        if (fullTimeFacultyGradBtn) fullTimeFacultyGradBtn.addEventListener('click', function () { updateFullTimeFacultyLevelUI('graduate'); });

        var researchCatCollaborative = document.getElementById('research-cat-collaborative');
        if (researchCatCollaborative) researchCatCollaborative.addEventListener('click', function () { updateResearchCategoryUI('collaborative'); });
        var researchCatSole = document.getElementById('research-cat-sole');
        if (researchCatSole) researchCatSole.addEventListener('click', function () { updateResearchCategoryUI('sole'); });
        var researchCatPublished = document.getElementById('research-cat-published');
        if (researchCatPublished) researchCatPublished.addEventListener('click', function () { updateResearchCategoryUI('published'); });
        var researchCatCitations = document.getElementById('research-cat-citations');
        if (researchCatCitations) researchCatCitations.addEventListener('click', function () { updateResearchCategoryUI('citations'); });

        var researchPublishedToggle = document.getElementById('research-published-toggle');
        if (researchPublishedToggle) {
            researchPublishedToggle.addEventListener('click', function () {
                var current = document.getElementById('research-published-value');
                var isPublished = !current || current.value !== 'published';
                updateResearchPublishedUI(isPublished);
            });
        }

        document.querySelectorAll('.classification-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                var intl = document.getElementById('classification-intl');
                var loc = document.getElementById('classification-local');
                if (!intl || !loc) return;
                var isIntl = pill.getAttribute('data-value') === 'international';
                intl.classList.toggle('bg-primary', isIntl);
                intl.classList.toggle('text-on-primary', isIntl);
                intl.classList.toggle('shadow-sm', isIntl);
                intl.classList.toggle('text-on-surface-variant', !isIntl);
                intl.classList.toggle('hover:bg-surface-container', !isIntl);
                loc.classList.toggle('bg-primary', !isIntl);
                loc.classList.toggle('text-on-primary', !isIntl);
                loc.classList.toggle('shadow-sm', !isIntl);
                loc.classList.toggle('text-on-surface-variant', isIntl);
                loc.classList.toggle('hover:bg-surface-container', isIntl);
            });
        });

        // (membership-filter) input filtering is handled by applyMembershipFilters()

        // Membership row actions (kebab menu -> Edit/Delete)
        var membershipTbody = document.getElementById('membership-table-body');
        if (membershipTbody) {
            var activeMembershipRow = null;
            var moveMembershipRow = function (row, direction) {
                if (!row || !membershipTbody) return;
                if (direction === 'up') {
                    var prev = row.previousElementSibling;
                    if (prev) {
                        membershipTbody.insertBefore(row, prev);
                    }
                    return;
                }
                if (direction === 'down') {
                    var next = row.nextElementSibling;
                    if (next) {
                        membershipTbody.insertBefore(next, row);
                    }
                }
            };

            var menu = document.getElementById('membership-row-actions-menu');
            if (!menu) {
                menu = document.createElement('div');
                menu.id = 'membership-row-actions-menu';
                menu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                menu.setAttribute('role', 'menu');
                menu.innerHTML =
                    '<button type="button" id="membership-row-edit-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Edit</button>' +
                    '<button type="button" id="membership-row-delete-btn" class="block w-full px-4 py-3 text-left text-xs font-bold text-on-surface-variant hover:bg-surface-container transition-colors" role="menuitem">Delete</button>';
                document.body.appendChild(menu);
            }

            var closeMenu = function () {
                if (menu) menu.classList.add('hidden');
                activeMembershipRow = null;
            };

            var openMenuForBtn = function (btn) {
                activeMembershipRow = btn ? btn.closest('tr') : null;
                if (!activeMembershipRow) return;
                var rect = btn.getBoundingClientRect();
                menu.style.left = Math.max(12, rect.left) + 'px';
                menu.style.top = Math.min(window.innerHeight - 64, rect.bottom + 8) + 'px';
                menu.classList.remove('hidden');
            };

            membershipTbody.addEventListener('click', function (e) {
                var upBtn = e.target.closest('.membership-row-move-up-btn');
                if (upBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    moveMembershipRow(upBtn.closest('tr'), 'up');
                    return;
                }

                var downBtn = e.target.closest('.membership-row-move-down-btn');
                if (downBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    moveMembershipRow(downBtn.closest('tr'), 'down');
                    return;
                }

                var btn = e.target.closest('.membership-row-menu-btn');
                if (!btn) return;
                e.stopPropagation();
                openMenuForBtn(btn);
            });

            membershipTbody.addEventListener('keydown', function (e) {
                var btn = e.target.closest('.membership-row-menu-btn');
                if (!btn) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    openMenuForBtn(btn);
                }
            });

            var editBtn = document.getElementById('membership-row-edit-btn');
            if (editBtn) {
                editBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (!activeMembershipRow) return;

                    var idVal = activeMembershipRow.getAttribute('data-membership-id');
                    var orgVal = activeMembershipRow.getAttribute('data-membership-org') || '';
                    var typeVal = activeMembershipRow.getAttribute('data-membership-type') || 'International';
                    var statusVal = activeMembershipRow.getAttribute('data-membership-status') || '';
                    var yearVal = activeMembershipRow.getAttribute('data-membership-year') || '';
                    var yearEndVal = activeMembershipRow.getAttribute('data-membership-year-end') || '';

                    openMembershipModal();
                    editingMembershipId = idVal ? parseInt(idVal, 10) : null;

                    var orgEl = document.getElementById('org_name');
                    var yearEl = document.getElementById('membership_year');
                    var statusEl = document.getElementById('membership_status');
                    var customStatusEl = document.getElementById('membership_status_custom');

                    if (orgEl) orgEl.value = orgVal;
                    var normalizedStatus = normalizeMembershipStatusForDisplay(statusVal);
                    if (yearEl) {
                        var startYear = parseInt(yearVal, 10);
                        var endYear = parseInt(yearEndVal, 10);
                        yearEl.value = (!isNaN(startYear) && !isNaN(endYear) && endYear >= startYear)
                            ? (String(startYear) + ' - ' + String(endYear))
                            : yearVal;
                    }
                    setMembershipClassification(typeVal);

                    var isPredefined = normalizedStatus === 'Annual' || normalizedStatus === 'Lifetime' || normalizedStatus === 'Autonomous';
                    if (statusEl) statusEl.value = isPredefined ? normalizedStatus : '';
                    if (customStatusEl) customStatusEl.value = isPredefined ? '' : (statusVal || '');

                    closeMenu();
                });
            }

            var delBtn = document.getElementById('membership-row-delete-btn');
            if (delBtn) {
                delBtn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    if (!activeMembershipRow) return;
                    var orgCell = activeMembershipRow.querySelector('td[data-col="org"]') || activeMembershipRow.querySelector('td:nth-child(2)');
                    var orgText = (orgCell && orgCell.textContent) ? orgCell.textContent.trim() : 'this record';
                    var ok = window.confirm('Delete membership record for "' + orgText + '"?');
                    if (!ok) return;
                    var membershipId = activeMembershipRow.getAttribute('data-membership-id');
                    try {
                        if (membershipId) {
                            var resp = await fetch('api/mobility-memberships.php?id=' + encodeURIComponent(membershipId), { method: 'DELETE', credentials: 'same-origin' });
                            var result = await resp.json();
                            if (!resp.ok || !result || !result.success) {
                                throw new Error((result && result.error) ? result.error : 'Delete failed');
                            }
                            if (typeof loadMemberships === 'function') {
                                await loadMemberships();
                            }
                        } else {
                            // Fallback: if we can't find an ID, remove the row locally.
                            activeMembershipRow.remove();
                        }
                    } catch (_) {
                        window.alert('Failed to delete membership from the server.');
                    }
                    closeMenu();
                });
            }

            document.addEventListener('click', function () {
                closeMenu();
            });
            window.addEventListener('scroll', function () {
                closeMenu();
            }, true);
        }

        function computeMembershipValidity(yearVal, yearEndVal) {
            var y = parseInt(yearVal, 10);
            var yEnd = parseInt(yearEndVal, 10);
            if ((yearVal === null || yearVal === undefined || String(yearVal).trim() === '') &&
                (yearEndVal === null || yearEndVal === undefined || String(yearEndVal).trim() === '')) {
                return '—';
            }
            if (!isNaN(y) && !isNaN(yEnd) && yEnd >= y) return String(y) + '-' + String(yEnd);
            return String(yearVal || '');
        }

        function normalizeMembershipStatusForDisplay(rawStatus) {
            var raw = String(rawStatus || '').trim();
            if (!raw) return '';
            var v = raw.toLowerCase();
            if (v === 'autonomous' || v === 'autonomous status') return 'Autonomous';
            if (v === 'lifetime') return 'Lifetime';
            if (v === 'annual') return 'Annual';
            // Preserve custom statuses entered by users.
            return raw;
        }

        function getMembershipStatusPillClass(status) {
            if (status === 'Lifetime') return 'bg-indigo-100 text-indigo-700';
            if (status === 'Autonomous') return 'bg-amber-100 text-amber-700';
            if (status === 'Annual') return 'bg-green-100 text-green-700';
            return 'bg-surface-container-high text-on-surface-variant';
        }

        function renderMemberships(entries) {
            if (!membershipTbody) return;
            membershipTbody.innerHTML = '';

            var rows = entries || [];
            if (rows.length === 0) {
                var tr = document.createElement('tr');
                tr.id = 'membership-empty-row';
                tr.innerHTML = '<td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">No membership records yet. Click <strong class="text-on-surface">Add New Record</strong> to create one.</td>';
                membershipTbody.appendChild(tr);
                return;
            }

            rows.forEach(function (r) {
                var tr = document.createElement('tr');
                tr.className = 'group hover:bg-surface-variant/30 transition-colors membership-row';
                tr.setAttribute('data-membership-id', r.id);
                tr.setAttribute('data-membership-org', r.org_name || '');
                tr.setAttribute('data-membership-type', r.membership_type || '');
                tr.setAttribute('data-membership-status', r.membership_status || '');
                tr.setAttribute('data-membership-year', r.membership_year || '');
                tr.setAttribute('data-membership-year-end', r.membership_year_end || '');
                var normalizedStatus = normalizeMembershipStatusForDisplay(r.membership_status);
                var statusLabel = normalizedStatus || '—';
                var statusPillClass = getMembershipStatusPillClass(normalizedStatus);
                var validity = computeMembershipValidity(r.membership_year, r.membership_year_end);

                tr.innerHTML =
                    '<td class="py-4 w-12 px-0 align-middle">' +
                        '<div class="flex items-center justify-center">' +
                            '<input type="checkbox" class="membership-row-checkbox block m-0 p-0 w-4 h-4 rounded border-outline-variant/20 text-primary focus:ring-primary/30" aria-label="Select membership row" />' +
                        '</div>' +
                    '</td>' +
                    '<td class="py-4 text-on-surface font-medium text-xs" data-col="org">' + (r.org_name || '') + '</td>' +
                    '<td class="py-4 text-on-surface-variant text-xs font-medium" data-col="type">' + (r.membership_type || '') + '</td>' +
                    '<td class="py-4" data-col="status"><span class="px-3 py-0.5 text-xs font-bold rounded-full leading-none ' + statusPillClass + '">' + statusLabel + '</span></td>' +
                    '<td class="py-4 text-on-surface-variant text-xs font-medium" data-col="validity">' + validity + '</td>' +
                    '<td class="py-4 text-right">' +
                        '<div class="flex items-center justify-end gap-1">' +
                            '<button type="button" class="membership-row-move-up-btn material-symbols-outlined text-on-surface-variant/40 hover:text-on-surface transition-colors" aria-label="Move row up">keyboard_arrow_up</button>' +
                            '<button type="button" class="membership-row-move-down-btn material-symbols-outlined text-on-surface-variant/40 hover:text-on-surface transition-colors" aria-label="Move row down">keyboard_arrow_down</button>' +
                            '<span class="membership-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0" aria-label="Row actions">more_vert</span>' +
                        '</div>' +
                    '</td>';

                membershipTbody.appendChild(tr);
            });
        }

        async function loadMemberships() {
            if (!membershipTbody) return;
            // Show loading state (avoid "hard-coded empty" flicker).
            try {
                membershipTbody.innerHTML = '<tr id="membership-loading-row"><td colspan="6" class="py-10 text-center text-on-surface-variant text-sm">Loading…</td></tr>';
            } catch (_) {}
            try {
                var resp = await fetch('api/mobility-memberships.php?ts=' + Date.now(), { method: 'GET', credentials: 'same-origin' });
                var result = await resp.json();
                if (!resp.ok || !result || !result.success) {
                    throw new Error((result && result.error) ? result.error : 'Failed to load memberships');
                }
                renderMemberships(result.data || []);

                // Reset header checkbox state after re-render.
                try {
                    var selAll = document.getElementById('membership-select-all');
                    if (selAll) selAll.checked = false;
                    membershipSelectAllClicked = false;
                    if (typeof syncBulkDeleteState === 'function') syncBulkDeleteState();
                } catch (_) {}
            } catch (_) {
                // Keep empty state if server is unavailable.
                renderMemberships([]);
            }
        }

        // Initial load from backend so data persists across refresh.
        loadMemberships();
        loadLinkages();
        loadStudentMobility();
        loadScholarships();
        loadStaffMobility();
        loadFullTimeStudents();
        loadFullTimeFaculty();
        loadResearchRecords();
        loadTnRecords();
        loadCoilRecords();
        loadCollaborativeEvents();
        loadInhouseEvents();
        loadIscRecords();
        loadStudyphRecords();

        // Membership filters + bulk selection
        var membershipFilterBtn = document.getElementById('membership-filter-btn');
        var membershipFilterPanel = document.getElementById('membership-filter-panel');
        if (membershipFilterBtn && membershipFilterPanel) {
            membershipFilterBtn.addEventListener('click', function () {
                membershipFilterPanel.classList.toggle('hidden');
            });
        }

        var membershipFilterClearBtn = document.getElementById('membership-filter-clear');
        var membershipFilterApplyBtn = document.getElementById('membership-filter-apply');
        var membershipFilterType = document.getElementById('membership-filter-type');
        var membershipFilterStatus = document.getElementById('membership-filter-status');
        var membershipFilterText = document.getElementById('membership-filter');
        var membershipSelectAll = document.getElementById('membership-select-all');
        var membershipBulkDeleteBtn = document.getElementById('membership-bulk-delete-btn');
        var membershipSelectAllClicked = false;

        function applyMembershipFilters() {
            var q = membershipFilterText ? (membershipFilterText.value || '').toLowerCase().trim() : '';
            var typeVal = membershipFilterType ? membershipFilterType.value : 'ALL';
            var statusVal = membershipFilterStatus ? membershipFilterStatus.value : 'ALL';

            membershipTbody.querySelectorAll('.membership-row').forEach(function (row) {
                var text = row.textContent.toLowerCase();
                var orgOk = !q || text.indexOf(q) !== -1;

                var typeCell = row.querySelector('td[data-col="type"]');
                var statusCell = row.querySelector('td[data-col="status"] span');
                var typeText = typeCell ? typeCell.textContent.trim().toUpperCase() : '';
                var statusText = statusCell ? statusCell.textContent.trim().toUpperCase() : '';

                var typeOk = typeVal === 'ALL' || typeText.indexOf(typeVal) !== -1;
                var statusOk = statusVal === 'ALL' || statusText === statusVal;

                row.style.display = orgOk && typeOk && statusOk ? '' : 'none';
            });
        }

        function syncBulkDeleteState() {
            if (!membershipBulkDeleteBtn) return;
            var headerChecked = membershipSelectAll ? !!membershipSelectAll.checked : false;
            var show = headerChecked && membershipSelectAllClicked;
            membershipBulkDeleteBtn.disabled = !show;
            membershipBulkDeleteBtn.classList.toggle('hidden', !show);
        }

        if (membershipFilterClearBtn) {
            membershipFilterClearBtn.addEventListener('click', function () {
                if (membershipFilterType) membershipFilterType.value = 'ALL';
                if (membershipFilterStatus) membershipFilterStatus.value = 'ALL';
                if (membershipFilterText) membershipFilterText.value = '';
                applyMembershipFilters();
                syncBulkDeleteState();
            });
        }
        if (membershipFilterApplyBtn) {
            membershipFilterApplyBtn.addEventListener('click', function () {
                applyMembershipFilters();
            });
        }

        if (membershipFilterText) membershipFilterText.addEventListener('input', function () { applyMembershipFilters(); });
        if (membershipFilterType) membershipFilterType.addEventListener('change', function () { applyMembershipFilters(); });
        if (membershipFilterStatus) membershipFilterStatus.addEventListener('change', function () { applyMembershipFilters(); });

        if (membershipSelectAll) {
            membershipSelectAll.addEventListener('change', function () {
                var checked = !!membershipSelectAll.checked;
                membershipSelectAllClicked = checked;
                membershipTbody.querySelectorAll('.membership-row').forEach(function (row) {
                    // Respect current text/status filtering (only select visible rows).
                    var visible = row.style.display !== 'none';
                    if (!visible) return;
                    var cb = row.querySelector('.membership-row-checkbox');
                    if (cb) cb.checked = checked;
                });
                syncBulkDeleteState();
            });
        }

        membershipTbody.addEventListener('change', function (e) {
            var target = e.target;
            if (!target || !target.classList || !target.classList.contains('membership-row-checkbox')) return;
            if (membershipSelectAll) {
                // Update "select all" based on visible rows only.
                var visibleRows = Array.prototype.slice.call(membershipTbody.querySelectorAll('.membership-row')).filter(function (r) { return r.style.display !== 'none'; });
                var allChecked = visibleRows.length > 0 && visibleRows.every(function (r) { var cb = r.querySelector('.membership-row-checkbox'); return cb && cb.checked; });
                membershipSelectAll.checked = allChecked;
            }
            syncBulkDeleteState();
        });

        if (membershipBulkDeleteBtn) {
            membershipBulkDeleteBtn.addEventListener('click', async function () {
                var checkedBoxes = membershipTbody.querySelectorAll('.membership-row-checkbox:checked');
                if (!checkedBoxes || checkedBoxes.length === 0) return;

                var ids = [];
                checkedBoxes.forEach(function (cb) {
                    var row = cb.closest('tr');
                    if (!row) return;
                    var id = row.getAttribute('data-membership-id');
                    if (id) ids.push(id);
                });

                if (ids.length > 0) {
                    var ok = window.confirm('Delete ' + ids.length + ' membership record(s)?');
                    if (!ok) return;

                    try {
                        for (var i = 0; i < ids.length; i++) {
                            var resp = await fetch('api/mobility-memberships.php?id=' + encodeURIComponent(ids[i]), { method: 'DELETE', credentials: 'same-origin' });
                            var result = await resp.json();
                            if (!resp.ok || !result || !result.success) {
                                throw new Error((result && result.error) ? result.error : 'Delete failed');
                            }
                        }
                        if (typeof loadMemberships === 'function') await loadMemberships();
                    } catch (_) {
                        window.alert('Failed to delete membership records from the server.');
                    }
                } else {
                    // Fallback: DOM-only removal (shouldn't happen once rows come from backend).
                    checkedBoxes.forEach(function (cb) {
                        var row = cb.closest('tr');
                        if (row) row.remove();
                    });
                }

                if (membershipSelectAll) membershipSelectAll.checked = false;
                syncBulkDeleteState();
                closeMenu();
            });
        }
    })();
    </script>
</body>
</html>
