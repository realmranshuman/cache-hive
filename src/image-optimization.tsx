import * as React from "react";
import { useState } from "react";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Slider } from "@/components/ui/slider";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Checkbox } from "@/components/ui/checkbox";
import { Textarea } from "@/components/ui/textarea";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { Badge } from "@/components/ui/badge";
import { InfoIcon } from "lucide-react";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";

export function ImageOptimizationSettings() {
  const [optimizationLibrary, setOptimizationLibrary] = useState("gd");
  const [optimizeLosslessly, setOptimizeLosslessly] = useState(true);
  const [optimizeOriginal, setOptimizeOriginal] = useState(true);
  const [nextGenFormat, setNextGenFormat] = useState("webp");
  const [imageQuality, setImageQuality] = useState([80]);
  const [deliveryMethod, setDeliveryMethod] = useState("rewrite");
  const [removeExif, setRemoveExif] = useState(true);
  const [autoResize, setAutoResize] = useState(false);
  const [maxWidth, setMaxWidth] = useState("1920");
  const [maxHeight, setMaxHeight] = useState("1080");
  const [batchProcessing, setBatchProcessing] = useState(false);
  const [batchSize, setBatchSize] = useState("10");
  const [excludeImages, setExcludeImages] = useState("");
  const [selectedThumbnails, setSelectedThumbnails] = useState<string[]>([
    "thumbnail",
    "medium",
  ]);

  // Mock thumbnail sizes - in real app this would be dynamic from WordPress
  const thumbnailSizes = [
    { id: "thumbnail", name: "Thumbnail", size: "150x150" },
    { id: "medium", name: "Medium", size: "300x300" },
    { id: "medium_large", name: "Medium Large", size: "768x0" },
    { id: "large", name: "Large", size: "1024x1024" },
    { id: "full", name: "Full Size", size: "Original" },
  ];

  const handleThumbnailToggle = (thumbnailId: string) => {
    setSelectedThumbnails((prev) =>
      prev.includes(thumbnailId)
        ? prev.filter((id) => id !== thumbnailId)
        : [...prev, thumbnailId]
    );
  };

  const handleSave = () => {
    // Handle save logic here
    console.log("Settings saved!");
  };

  const handleReset = () => {
    // Reset to defaults
    setOptimizationLibrary("gd");
    setOptimizeLosslessly(true);
    setImageQuality([80]);
    setDeliveryMethod("rewrite");
    setRemoveExif(true);
    setAutoResize(false);
    setMaxWidth("1920");
    setMaxHeight("1080");
    setBatchProcessing(false);
    setBatchSize("10");
    setExcludeImages("");
    setSelectedThumbnails(["thumbnail", "medium"]);
  };

  return (
    <TooltipProvider>
      <div className="space-y-8 bg-background min-h-screen py-8 px-4 md:px-8 w-full rounded-xl">
        {/* Library Selection */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              Library To Optimize Images
              <Tooltip>
                <TooltipTrigger>
                  <InfoIcon className="h-4 w-4 text-muted-foreground" />
                </TooltipTrigger>
                <TooltipContent>
                  <p>
                    Choose the image processing library. Options depend on
                    server capabilities.
                  </p>
                </TooltipContent>
              </Tooltip>
            </CardTitle>
            <CardDescription>
              Select the image processing library based on your server
              configuration.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="space-y-4">
              <Select
                value={optimizationLibrary}
                onValueChange={setOptimizationLibrary}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select optimization library" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="gd">
                    <div className="flex items-center gap-2">
                      GD Library
                      <Badge variant="secondary">WebP, AVIF Support</Badge>
                    </div>
                  </SelectItem>
                  <SelectItem value="imagemagick">
                    <div className="flex items-center gap-2">
                      ImageMagick
                      <Badge variant="secondary">v7.x+</Badge>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        {/* Image Types to Optimize */}
        <Card>
          <CardHeader>
            <CardTitle>Image Types to Optimize</CardTitle>
            <CardDescription>
              Choose which original images and thumbnail sizes should be
              optimized.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            {/* Optimize Original Images */}
            <div className="flex items-center justify-between">
              <Label htmlFor="optimize-original">
                Optimize Original Images
              </Label>
              <Switch
                id="optimize-original"
                checked={optimizeOriginal}
                onCheckedChange={setOptimizeOriginal}
              />
            </div>
            <Separator />
            {/* Thumbnail Sizes */}
            <div>
              <div className="mb-2 font-medium">
                Thumbnail Sizes To Optimize
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {thumbnailSizes.map((thumbnail) => (
                  <div
                    key={thumbnail.id}
                    className="flex items-center space-x-2"
                  >
                    <Checkbox
                      id={thumbnail.id}
                      checked={selectedThumbnails.includes(thumbnail.id)}
                      onCheckedChange={() =>
                        handleThumbnailToggle(thumbnail.id)
                      }
                    />
                    <Label htmlFor={thumbnail.id} className="flex-1">
                      <div className="flex items-center justify-between">
                        <span>{thumbnail.name}</span>
                        <Badge variant="outline">{thumbnail.size}</Badge>
                      </div>
                    </Label>
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Serve images in next-gen formats */}
        <Card>
          <CardHeader>
            <CardTitle>Serve images in next-gen formats</CardTitle>
            <CardDescription>
              Choose which next-gen format to serve for supported browsers.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Select value={nextGenFormat} onValueChange={setNextGenFormat}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Select format" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="webp">WebP</SelectItem>
                <SelectItem value="avif">AVIF</SelectItem>
              </SelectContent>
            </Select>
          </CardContent>
        </Card>

        {/* Lossless Optimization */}
        <Card>
          <CardHeader>
            <CardTitle>Lossless Optimization</CardTitle>
            <CardDescription>
              Optimize images without losing quality. Disable to adjust quality
              manually.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="flex items-center justify-between">
              <Label htmlFor="lossless">Optimize Losslessly</Label>
              <Switch
                id="lossless"
                checked={optimizeLosslessly}
                onCheckedChange={setOptimizeLosslessly}
              />
            </div>

            {!optimizeLosslessly && (
              <div className="space-y-4">
                <Separator />
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <Label>Image Quality: {imageQuality[0]}%</Label>
                    <Badge variant="outline">{imageQuality[0]}%</Badge>
                  </div>
                  <Slider
                    value={imageQuality}
                    onValueChange={setImageQuality}
                    min={40}
                    max={98}
                    step={2}
                    className="w-full"
                  />
                  <p className="text-sm text-muted-foreground">
                    Quality range is 40-98% to balance file size and visual
                    quality. Lower values create smaller files but may reduce
                    image quality.
                  </p>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Delivery Method */}
        <Card>
          <CardHeader>
            <CardTitle>Image Delivery Method</CardTitle>
            <CardDescription>
              Choose how optimized images are served to visitors.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <RadioGroup
              value={deliveryMethod}
              onValueChange={setDeliveryMethod}
            >
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="rewrite" id="rewrite" />
                <Label htmlFor="rewrite">Use Rewrite Rule</Label>
              </div>
              <div className="flex items-center space-x-2">
                <RadioGroupItem value="picture" id="picture" />
                <Label htmlFor="picture">Use &lt;picture&gt; tag</Label>
              </div>
            </RadioGroup>
          </CardContent>
        </Card>

        {/* Additional Options */}
        <Card>
          <CardHeader>
            <CardTitle>Additional Options</CardTitle>
            <CardDescription>
              Configure additional optimization features and settings.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="remove-exif">Remove EXIF Data</Label>
                <p className="text-sm text-muted-foreground">
                  Strip metadata from images to reduce file size
                </p>
              </div>
              <Switch
                id="remove-exif"
                checked={removeExif}
                onCheckedChange={setRemoveExif}
              />
            </div>

            <Separator />

            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="auto-resize">Auto Resize Large Images</Label>
                  <p className="text-sm text-muted-foreground">
                    Automatically resize images that exceed maximum dimensions
                  </p>
                </div>
                <Switch
                  id="auto-resize"
                  checked={autoResize}
                  onCheckedChange={setAutoResize}
                />
              </div>

              {autoResize && (
                <div className="grid grid-cols-2 gap-4 pl-4">
                  <div className="space-y-2">
                    <Label htmlFor="max-width">Maximum Width (px)</Label>
                    <Input
                      id="max-width"
                      type="number"
                      value={maxWidth}
                      onChange={(e) => setMaxWidth(e.target.value)}
                      placeholder="1920"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="max-height">Maximum Height (px)</Label>
                    <Input
                      id="max-height"
                      type="number"
                      value={maxHeight}
                      onChange={(e) => setMaxHeight(e.target.value)}
                      placeholder="1080"
                    />
                  </div>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Thumbnail Sizes */}
        <Card>
          <CardHeader>
            <CardTitle>Thumbnail Sizes To Optimize</CardTitle>
            <CardDescription>
              Select which thumbnail sizes should be optimized. These are loaded
              from your WordPress theme settings.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {thumbnailSizes.map((thumbnail) => (
                <div key={thumbnail.id} className="flex items-center space-x-2">
                  <Checkbox
                    id={thumbnail.id}
                    checked={selectedThumbnails.includes(thumbnail.id)}
                    onCheckedChange={() => handleThumbnailToggle(thumbnail.id)}
                  />
                  <Label htmlFor={thumbnail.id} className="flex-1">
                    <div className="flex items-center justify-between">
                      <span>{thumbnail.name}</span>
                      <Badge variant="outline">{thumbnail.size}</Badge>
                    </div>
                  </Label>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Exclude Images */}
        <Card>
          <CardHeader>
            <CardTitle>Exclude Images From Optimizations</CardTitle>
            <CardDescription>
              Enter image filenames or patterns to exclude from optimization
              (one per line).
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Textarea
              value={excludeImages}
              onChange={(e) => setExcludeImages(e.target.value)}
              placeholder="logo.png&#10;header-*.jpg&#10;/uploads/2023/special-image.png"
              rows={4}
            />
          </CardContent>
        </Card>

        {/* Batch Processing */}
        <Card>
          <CardHeader>
            <CardTitle>Process Images In Batches Cron</CardTitle>
            <CardDescription>
              Enable batch processing to optimize images in background using
              cron jobs.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between">
              <div className="space-y-0.5">
                <Label htmlFor="batch-processing">
                  Enable Batch Processing
                </Label>
                <p className="text-sm text-muted-foreground">
                  Process images in scheduled batches to reduce server load
                </p>
              </div>
              <Switch
                id="batch-processing"
                checked={batchProcessing}
                onCheckedChange={setBatchProcessing}
              />
            </div>

            {batchProcessing && (
              <div className="space-y-2 pl-4">
                <Label htmlFor="batch-size">Images Per Batch</Label>
                <Input
                  id="batch-size"
                  type="number"
                  value={batchSize}
                  onChange={(e) => setBatchSize(e.target.value)}
                  min="5"
                  max="50"
                  placeholder="10"
                />
                <p className="text-sm text-muted-foreground">
                  Recommended: 5-50 images per batch for shared hosting
                  environments. Default: 10
                </p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Action Button */}
        <div className="flex justify-end">
          <Button onClick={handleSave}>Save Settings</Button>
        </div>
      </div>
    </TooltipProvider>
  );
}
