document.addEventListener('DOMContentLoaded', () => {
    Alpine.store("mobileSidebar", {
      isClosed: Alpine.$persist(false).as("isClosed"),
      close() {
        this.isClosed = true;
      },
    });

    const filamentSidebarCloseOverlay = document.querySelector('.filament-sidebar-close-overlay');

    if (! Alpine.store('mobileSidebar').isClosed && filamentSidebarCloseOverlay) {
      if (window.innerWidth <= 768) {
        filamentSidebarCloseOverlay.click();
        Alpine.store('mobileSidebar').close();
      }
    }
  });