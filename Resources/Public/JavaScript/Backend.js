(function () {
    const button = document.getElementById("build-error-message-button");
    const container = document.getElementById("build-error-message");
    if (button && container) {
        function show() {
            container.hidden = false;
            button.setAttribute("aria-expanded", "true");
        }

        function hide() {
            container.hidden = true;
            button.setAttribute("aria-expanded", "false");
        }

        button.addEventListener("click", () => {
            if (container.hidden) {
                show();
            } else {
                hide();
            }
        });
    }
})();
