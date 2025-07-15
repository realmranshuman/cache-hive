"use client"

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from "recharts/es6"
import { CheckCircle, XCircle, ImageIcon, TrendingUp } from "lucide-react"
import * as React from "react"

const cacheHitData = [
	{ time: "00:00", percentage: 85 },
	{ time: "04:00", percentage: 78 },
	{ time: "08:00", percentage: 92 },
	{ time: "12:00", percentage: 88 },
	{ time: "16:00", percentage: 95 },
	{ time: "20:00", percentage: 90 },
]

export function Dashboard() {
	return (
		<div className="space-y-6">
			<div className="grid grid-cols-1 md:grid-cols-3 gap-6">
				{/* Cache Status Card */}
				<Card>
					<CardHeader>
						<CardTitle className="flex items-center space-x-2">
							<TrendingUp className="h-5 w-5" />
							<span>Cache Status</span>
						</CardTitle>
					</CardHeader>
					<CardContent className="space-y-3">
						<div className="flex items-center justify-between">
							<span className="text-sm font-medium">Public Cache</span>
							<Badge variant="default" className="bg-green-100 text-green-800">
								<CheckCircle className="h-3 w-3 mr-1" />
								Active
							</Badge>
						</div>
						<div className="flex items-center justify-between">
							<span className="text-sm font-medium">Private Cache</span>
							<Badge variant="default" className="bg-green-100 text-green-800">
								<CheckCircle className="h-3 w-3 mr-1" />
								Active
							</Badge>
						</div>
						<div className="flex items-center justify-between">
							<span className="text-sm font-medium">Object Cache</span>
							<Badge variant="secondary" className="bg-red-100 text-red-800">
								<XCircle className="h-3 w-3 mr-1" />
								Inactive
							</Badge>
						</div>
						<div className="flex items-center justify-between">
							<span className="text-sm font-medium">Browser Cache</span>
							<Badge variant="default" className="bg-green-100 text-green-800">
								<CheckCircle className="h-3 w-3 mr-1" />
								Active
							</Badge>
						</div>
					</CardContent>
				</Card>

				{/* Image Optimization Summary Card */}
				<Card>
					<CardHeader>
						<CardTitle className="flex items-center space-x-2">
							<ImageIcon className="h-5 w-5" />
							<span>Image Optimization Summary</span>
						</CardTitle>
					</CardHeader>
					<CardContent className="space-y-4">
						<div className="text-center">
							<div className="text-3xl font-bold text-blue-600">1,247</div>
							<div className="text-sm text-gray-600">Images Optimized</div>
						</div>
						<div className="text-center">
							<div className="text-2xl font-bold text-green-600">68%</div>
							<div className="text-sm text-gray-600">Size Reduction</div>
						</div>
						<div className="text-center">
							<div className="text-lg font-semibold text-purple-600">2.4 MB</div>
							<div className="text-sm text-gray-600">Total Saved</div>
						</div>
					</CardContent>
				</Card>

				{/* Cloudflare Cache Hits Graph */}
				<Card>
					<CardHeader>
						<CardTitle>Cloudflare Cache Hits</CardTitle>
					</CardHeader>
					<CardContent>
						<div className="h-48">
							<ResponsiveContainer width="100%" height="100%">
								<LineChart data={cacheHitData}>
									<CartesianGrid strokeDasharray="3 3" />
									<XAxis dataKey="time" />
									<YAxis domain={[0, 100]} />
									<Tooltip formatter={(value) => [`${value}%`, "Cache Hit Rate"]} />
									<Line
										type="monotone"
										dataKey="percentage"
										stroke="#3b82f6"
										strokeWidth={2}
										dot={{ fill: "#3b82f6", strokeWidth: 2, r: 4 }}
									/>
								</LineChart>
							</ResponsiveContainer>
						</div>
						<div className="mt-4 text-center">
							<div className="text-2xl font-bold text-blue-600">89%</div>
							<div className="text-sm text-gray-600">Average Cache Hit Rate</div>
						</div>
					</CardContent>
				</Card>
			</div>
		</div>
	)
}
