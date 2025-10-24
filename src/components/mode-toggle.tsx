import * as React from "@wordpress/element";
import { Moon, Sun } from "lucide-react";
import { useTheme } from "@/components/theme-provider";
import { Button } from "@/components/ui/button";

export function ModeToggle() {
  const { theme, setTheme } = useTheme();
  const isDark = theme === "dark";

  return (
    <Button
      variant="outline"
      size="icon"
      aria-label="Toggle theme"
      onClick={() => setTheme(isDark ? "light" : "dark")}
      className="relative transition-colors"
    >
      <Sun
        className={`h-[1.2rem] w-[1.2rem] transition-all duration-300 ${
          isDark ? "opacity-0 scale-75" : "opacity-100 scale-100"
        }`}
      />
      <Moon
        className={`absolute left-1/2 top-1/2 h-[1.2rem] w-[1.2rem] -translate-x-1/2 -translate-y-1/2 transition-all duration-300 ${
          isDark ? "opacity-100 scale-100" : "opacity-0 scale-75"
        }`}
      />
      <span className="sr-only">Toggle theme</span>
    </Button>
  );
}
