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
                                    <th class="px-4 py-5 text-sm font-bold text-center">2025 (Proj)</th>
                                </tr>
                            </thead>
                            <tbody id="awards-table-body" class="divide-y divide-surface-container-low">
                                <tr id="awards-loading-row">
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-on-surface-variant">Loading awards records...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="relative h-64 rounded-2xl overflow-hidden group shadow-md">
                            <img alt="University Campus" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBXje2VtRq4PcAWjFU-cdNwHEyPz-AV50f8ZYI9iCuh7pmwyBTFGMhkLRMuJoPvhms9BGVNgxuiloRHAmnV2IglfegvX1tRrlO0EyBMz3h08SCTgIin3Nsej7R9-E7zaXQXcwBrLDNiipDsnFByIWxL-18dugSWZiv99r7ohagzNTKNixieWou00qLNVLuY9SHSd_rY7n_Wp5EZ9UTqiKlrOKlK-MkXKDSpuWlkHG8QV-PPKilGqih4-h3SvBMNPRTjioBn14Bjr8E" />
                            <div class="absolute inset-0 bg-gradient-to-t from-primary/80 to-transparent flex flex-col justify-end p-6">
                                <h5 class="text-white text-lg font-bold">Institutional Growth</h5>
                                <p class="text-white/80 text-xs">Tracing the evolution of CPU's global footprint since 2019.</p>
                            </div>
                        </div>
                        <div class="bg-surface-container-highest/50 rounded-2xl p-6 border border-primary/10 flex flex-col justify-center">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary filled">insights</span>
                                </div>
                                <div>
                                    <div class="text-sm font-bold text-on-surface">Trend Analysis</div>
                                    <div class="text-xs text-on-surface-variant">Year-over-year mobility expansion</div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-semibold">Inbound Students</span>
                                    <div class="flex-1 mx-4 h-1.5 bg-surface-container rounded-full overflow-hidden">
                                        <div id="trend-inbound-students-bar" class="h-full bg-primary" style="width: 0%;"></div>
                                    </div>
                                    <span id="trend-inbound-students-label" class="text-xs font-bold text-primary">+0%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-semibold">Faculty Mobility</span>
                                    <div class="flex-1 mx-4 h-1.5 bg-surface-container rounded-full overflow-hidden">
                                        <div id="trend-faculty-mobility-bar" class="h-full bg-secondary" style="width: 0%;"></div>
                                    </div>
                                    <span id="trend-faculty-mobility-label" class="text-xs font-bold text-secondary">+0%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-semibold">Global Awards</span>
                                    <div class="flex-1 mx-4 h-1.5 bg-surface-container rounded-full overflow-hidden">
                                        <div id="trend-global-awards-bar" class="h-full bg-tertiary" style="width: 0%;"></div>
                                    </div>
                                    <span id="trend-global-awards-label" class="text-xs font-bold text-tertiary">+0%</span>
                                </div>
                            </div>
                        </div>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to enter a new linkage or partnership.</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to log inbound/outbound student mobility and internship details.</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to register inbound/outbound scholarship and fellowship programs.</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to register inbound/outbound staff mobility and scholarship activities.</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to create a full-time foreign student mobility entry.</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to create a full-time foreign faculty entry.</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to submit internationalized research records and citations.</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">Use <strong class="text-on-surface">Add New Record</strong> to enter a COIL class (partner, country, year, and subject).</p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">
                            Use <strong class="text-on-surface">Add New Record</strong> to create a transnational education record (partner, country, program, permit, and student stats).
                        </p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">
                            Use <strong class="text-on-surface">Add New Record</strong> to enter a collaborative event/activity (name, partners, countries, year, participants).
                        </p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">
                            Use <strong class="text-on-surface">Add New Record</strong> to record in-house ASEAN and institutional internationalization events.
                        </p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">
                            Use <strong class="text-on-surface">Add New Record</strong> to register an international/sustainability center (center name, partners, countries, year established).
                        </p>
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
                    <div class="rounded-xl border border-outline-variant/10 bg-surface-container-lowest p-6">
                        <p class="text-on-surface-variant text-sm">
                            Use <strong class="text-on-surface">Add New Record</strong> to add a StudyPH program entry (year, KRA, project title, scope, SDGs, and metrics).
                        </p>
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
                            <input class="w-full bg-surface-container-low border-none rounded-lg pl-3 pr-9 py-2 text-sm focus:ring-2 focus:ring-primary/30 text-on-surface transition-all" id="membership_year" name="membership_year" max="2100" min="1900" placeholder="2024" type="number" />
                            <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-lg pointer-events-none text-on-surface-variant">calendar_today</span>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-on-surface tracking-tight" for="membership_status">Status</label>
                        <div class="relative">
                            <select class="w-full appearance-none bg-surface-container-low border-none rounded-lg pl-3 pr-9 py-2 text-sm focus:ring-2 focus:ring-primary/30 text-on-surface transition-all" id="membership_status" name="membership_status">
                                <option>Annual</option>
                                <option>Lifetime</option>
                            </select>
                        </div>
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
                                        <option>Foreign HEI</option>
                                        <option>Private Org</option>
                                        <option>Governmental Body</option>
                                        <option>NGO</option>
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
                        <input class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm transition-all outline-none" placeholder="Search or enter faculty name" type="text" />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-on-surface tracking-wider block">FISCAL YEAR</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">calendar_today</span>
                        <select class="w-full pl-9 pr-8 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm appearance-none outline-none">
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
                        <textarea class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm outline-none resize-none" placeholder="Enter the full title of the research project..." rows="2"></textarea>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-on-surface tracking-wider block">PARTNER UNIVERSITY / AGENCY</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">school</span>
                            <input class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm outline-none" placeholder="Collaborating institution" type="text" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-on-surface tracking-wider block">PARTNER COUNTRY</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary-container">public</span>
                            <input class="w-full pl-9 pr-3 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/30 text-sm outline-none" placeholder="Search country" type="text" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-on-surface tracking-wider block">PROJECT STATUS</label>
                        <div class="flex gap-4 mt-1">
                            <label class="flex items-center gap-2 cursor-pointer"><input checked class="w-4 h-4 text-primary focus:ring-primary/30 border-outline" name="research-status" type="radio" /><span class="text-sm">Ongoing</span></label>
                            <label class="flex items-center gap-2 cursor-pointer"><input class="w-4 h-4 text-primary focus:ring-primary/30 border-outline" name="research-status" type="radio" /><span class="text-sm">Completed</span></label>
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
                    <div class="flex flex-wrap gap-2">
                        <div class="px-2.5 py-1.5 rounded-lg border border-outline-variant bg-surface-container-low text-[10px] font-medium">SDG 3: Good Health</div>
                        <div class="px-2.5 py-1.5 rounded-lg border-2 border-primary-container bg-primary-container/10 text-[10px] font-bold text-primary-container">SDG 4: Quality Education</div>
                        <div class="px-2.5 py-1.5 rounded-lg border border-outline-variant bg-surface-container-low text-[10px] font-medium">SDG 9: Industry & Innovation</div>
                        <button type="button" class="px-2.5 py-1.5 rounded-lg border-dashed border-2 border-outline-variant text-[10px] font-bold text-on-surface-variant">+ ADD SDG</button>
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

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
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
                tr.className = 'hover:bg-surface-container-low transition-colors group';
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
                    '<td class="px-4 py-4 text-center text-sm font-medium text-slate-400 italic">' + normalizeCounterValue(row.y2025_proj) + '</td>';
                awardsTbody.appendChild(tr);
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

        function closeMembershipModal() {
            var m = document.getElementById('membership-modal');
            if (!m) return;
            m.classList.add('hidden');
            m.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
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
            // Defaults
            var scopeVal = document.getElementById('student-scope-value');
            if (scopeVal) scopeVal.value = 'inbound';
            var durationVal = document.getElementById('student-duration-value');
            if (durationVal) durationVal.value = 'short';
            updateStudentScopeUI(true);
            updateStudentDurationUI('short');
        }

        function closeStudentMobilityModal() {
            var s = document.getElementById('student-mobility-entry-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
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
            // Defaults
            var scopeVal = document.getElementById('scholarship-scope-value');
            if (scopeVal) scopeVal.value = 'inbound';
            updateScholarshipScopeUI(true);
            updateScholarshipModalityUI('on-site');
        }

        function closeScholarshipsModal() {
            var s = document.getElementById('scholarships-entry-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
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
            updateStaffScopeUI(true);
            updateStaffModalityUI('physical');
        }

        function closeStaffMobilityModal() {
            var s = document.getElementById('staff-mobility-entry-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function openFullTimeForeignStudentsModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignFacultyModal();
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
            updateFullTimeModalityUI('on-site');
            updateFullTimeLevelUI('undergraduate');
        }

        function closeFullTimeForeignStudentsModal() {
            var s = document.getElementById('full-time-foreign-students-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function openFullTimeForeignFacultyModal() {
            closeMembershipModal();
            closeLinkagesModal();
            closeStudentMobilityModal();
            closeScholarshipsModal();
            closeStaffMobilityModal();
            closeFullTimeForeignStudentsModal();
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
            updateFullTimeFacultyScopeUI('inbound');
            updateFullTimeFacultyLevelUI('undergraduate');
        }

        function closeFullTimeForeignFacultyModal() {
            var s = document.getElementById('full-time-foreign-faculty-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
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
            updateResearchCategoryUI('collaborative');
            updateResearchPublishedUI(true);
        }

        function closeInternationalizationResearchModal() {
            var s = document.getElementById('internationalization-research-modal');
            if (!s) return;
            s.classList.add('hidden');
            s.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
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
        var saveBtn = document.getElementById('membership-modal-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', async function () {
                var orgEl = document.getElementById('org_name');
                var yearEl = document.getElementById('membership_year');
                var statusEl = document.getElementById('membership_status');
                var intlBtn = document.getElementById('classification-intl');

                var org = orgEl ? (orgEl.value || '').trim() : '';
                var yearVal = yearEl ? (yearEl.value || '').toString().trim() : '';
                var status = statusEl ? statusEl.value : '';

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

                if (org && yearVal && status) {
                    try {
                        var fd = new FormData();
                        fd.append('org_name', org);
                        fd.append('membership_type', type);
                        fd.append('membership_status', status);
                        fd.append('membership_year', yearVal);

                        var resp = await fetch('api/mobility-memberships.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                        var result = await resp.json();
                        if (!resp.ok || !result || !result.success) {
                            throw new Error((result && result.error) ? result.error : 'Failed to save membership');
                        }

                        if (typeof loadMemberships === 'function') {
                            await loadMemberships();
                        }
                    } catch (err) {
                        toastMembership(err && err.message ? err.message : 'Failed to save membership', true);
                        return;
                    }
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
            if (field) formData.append('description', field);
            if (agreement) formData.append('title', agreement);

            var response = await fetch('api/mou-moa.php', { method: 'POST', body: formData });
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

            toast(result.message || 'Entry saved successfully.', false);
            return result;
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
                saveLinkagesToDatabase({ isDraft: false }).finally(function () {
                    closeLinkagesModal();
                });
            });
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
                closeStudentMobilityModal();
            });
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
                closeScholarshipsModal();
            });
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
                closeStaffMobilityModal();
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
                closeFullTimeForeignStudentsModal();
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
                closeFullTimeForeignFacultyModal();
            });
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
                closeInternationalizationResearchModal();
            });
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
                closeTransnationalEducationProgramModal();
            });
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
                closeCollaborativeEventsActivitiesModal();
            });
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
                closeInHouseAseanModal();
            });
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
                closeInternationalSustainabilityCentersModal();
            });
        }

        var studyphCloseBtn = document.getElementById('studyph-program-modal-close');
        if (studyphCloseBtn) studyphCloseBtn.addEventListener('click', closeStudyPHProgramModal);
        var studyphBackdropEl = document.getElementById('studyph-program-modal-backdrop');
        if (studyphBackdropEl) studyphBackdropEl.addEventListener('click', closeStudyPHProgramModal);
        var studyphCancelBtn = document.getElementById('studyph-cancel');
        if (studyphCancelBtn) studyphCancelBtn.addEventListener('click', closeStudyPHProgramModal);
        var studyphForm = document.getElementById('studyph-program-form');
        if (studyphForm) {
            studyphForm.addEventListener('submit', function (e) {
                e.preventDefault();
                closeStudyPHProgramModal();
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
                closeCoilModal();
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
                var fullTimeFaculty = document.getElementById('full-time-foreign-faculty-modal');
                if (fullTimeFaculty && !fullTimeFaculty.classList.contains('hidden')) closeFullTimeForeignFacultyModal();
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

        // Membership row actions (kebab menu -> Delete)
        var membershipTbody = document.getElementById('membership-table-body');
        if (membershipTbody) {
            var activeMembershipRow = null;

            var menu = document.getElementById('membership-row-actions-menu');
            if (!menu) {
                menu = document.createElement('div');
                menu.id = 'membership-row-actions-menu';
                menu.className = 'fixed z-50 hidden bg-surface-container-lowest border border-outline-variant/10 rounded-xl shadow-2xl overflow-hidden';
                menu.setAttribute('role', 'menu');
                menu.innerHTML =
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

        function computeMembershipValidity(yearVal, status) {
            var y = parseInt(yearVal, 10);
            if (status === 'Lifetime') return String(yearVal) + ' — Perpetual';
            // Default: Annual -> year to year+5.
            return String(yearVal) + ' — ' + (isNaN(y) ? yearVal : (y + 5));
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
                var statusLabel = String(r.membership_status || '').toUpperCase();
                var validity = computeMembershipValidity(r.membership_year, r.membership_status);

                tr.innerHTML =
                    '<td class="py-4 w-12 px-0 align-middle">' +
                        '<div class="flex items-center justify-center">' +
                            '<input type="checkbox" class="membership-row-checkbox block m-0 p-0 w-4 h-4 rounded border-outline-variant/20 text-primary focus:ring-primary/30" aria-label="Select membership row" />' +
                        '</div>' +
                    '</td>' +
                    '<td class="py-4 text-on-surface font-medium text-xs" data-col="org">' + (r.org_name || '') + '</td>' +
                    '<td class="py-4 text-on-surface-variant text-xs font-medium" data-col="type">' + (r.membership_type || '') + '</td>' +
                    '<td class="py-4" data-col="status"><span class="px-3 py-0.5 bg-green-100 text-green-700 text-xs font-bold rounded-full leading-none">' + statusLabel + '</span></td>' +
                    '<td class="py-4 text-on-surface-variant text-xs font-medium" data-col="validity">' + validity + '</td>' +
                    '<td class="py-4 text-right"><span class="membership-row-menu-btn material-symbols-outlined text-on-surface-variant/40 group-hover:text-on-surface cursor-pointer" role="button" tabindex="0" aria-label="Row actions">more_vert</span></td>';

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
