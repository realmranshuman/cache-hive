import { Zap } from "lucide-react"
import * as React from "react"

export function Header() {
  return (
    <header className="bg-white border-b border-gray-200 shadow-sm">
      <div className="container mx-auto px-4 py-4">
        <div className="flex items-center space-x-3">
          <div className="bg-orange-500 p-2 rounded-lg">
            <Zap className="h-6 w-6 text-white" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Cache Hive</h1>
            <p className="text-sm text-gray-600">WordPress Caching & Optimization Plugin</p>
          </div>
        </div>
      </div>
    </header>
  )
}
