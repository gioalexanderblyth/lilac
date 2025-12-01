/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./*.php",
    "./api/**/*.php",
    "./awards/**/*.php",
    "./templates/**/*.{html,php}",
    "./**/*.php"
  ],
  // Use the `dark` class on <html> for dark mode so the JS toggle works with compiled CSS
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: "#137fec",
          "50": "#e8f2fe",
          "100": "#d1e6fd",
          "200": "#a2cbfb",
          "300": "#74b1f9",
          "400": "#4596f7",
          "500": "#137fec",
          "600": "#0f66bc",
          "700": "#0c4c8d",
          "800": "#08335d",
          "900": "#04192e",
        },
        "background-light": "#e8ecf1",
        "background-dark": "#0f172a",
        "card-light": "#fafbfc",
        "card-dark": "#1e293b",
        "text-light": "#1e293b",
        "text-dark": "#e2e8f0",
        "text-muted-light": "#64748b",
        "text-muted-dark": "#94a3b8",
        "border-light": "#d1d5db",
        "border-dark": "#334155",
        // KPI colors for analytics charts
        "kpi-blue": "#2563eb",
        "kpi-green": "#059669",
        "kpi-purple": "#7c3aed",
        "kpi-yellow": "#f59e0b",
        "kpi-indigo": "#4f46e5",
        "kpi-teal": "#0d9488",
        "kpi-orange": "#ea580c",
        "kpi-pink": "#ec4899",
        // Status chart colors
        "status-fully": "#3b82f6",
        "status-partial": "#a78bfa",
        "status-review": "#facc15",
        "status-unqualified": "#ef4444",
      },
      fontFamily: {
        display: ["Inter", "sans-serif"],
      },
      borderRadius: {
        DEFAULT: "0.5rem",
        lg: "0.75rem",
        xl: "1rem",
        full: "9999px",
      },
      boxShadow: {
        soft: "0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05)",
      },
    },
  },
  plugins: [
    require("@tailwindcss/forms"),
  ],
};


