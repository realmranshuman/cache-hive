import * as React from "@wordpress/element";
import { Zap } from "lucide-react";
import { ModeToggle } from "@/components/mode-toggle";

export function Header() {
  return (
    <header className="bg-white dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 shadow-sm">
      <div className="container mx-auto px-4 py-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="bg-orange-500 p-2 rounded-lg">
              <Zap className="h-6 w-6 text-white" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                Cache Hive
              </h1>
              <p className="text-sm text-gray-600 dark:text-gray-400">
                WordPress Caching & Optimization Plugin
              </p>
            </div>
          </div>
          <div className="flex items-center">
            <ModeToggle />
          </div>
        </div>
      </div>
    </header>
  );
}
