// tailwind.config.js
module.exports = {
  content: [
    "./*.{php,html,js}", // Para arquivos na raiz como index.html, home.php, etc.
    "./src/**/*.{html,js,php}", // Para arquivos dentro da pasta /src
  ],
  theme: {
    extend: {
      fontFamily: {
        poppins: ["Poppins", "sans-serif"],
        "roboto-mono": ['"Roboto Mono"', "monospace"], // Sua configuração da fonte Poppins
      },
    },
  },
  plugins: [
    // Se você instalou @tailwindcss/forms e quer usá-lo:
    require("@tailwindcss/forms"),
  ],
};
