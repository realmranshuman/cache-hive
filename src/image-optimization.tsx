import * as React from "react";
import { useState, Suspense, useCallback, useMemo, useEffect } from "react";
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
import { InfoIcon, Trash2Icon } from "lucide-react";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { ImageOptimizationSettingsSkeleton } from "@/components/skeletons/image-optimization-settings-skeleton";
import {
  getImageOptimizationSettings,
  updateImageOptimizationSettings,
  destroyAllImageOptimizationData,
  startImageSync,
  getImageSyncStatus,
  cancelImageSync,
  ImageOptimizationApiResponse,
  ImageOptimizationSettings as TImageOptimizationSettings,
} from "@/api/optimizers-image";
import { toast as sonnerToast } from "sonner";
import { wrapPromise } from "@/utils/wrapPromise";
import { ErrorBoundary } from "@/utils/ErrorBoundary";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Progress } from "@/components/ui/progress";
import { ChartConfig, ChartContainer } from "@/components/ui/chart";
import {
  PolarGrid,
  PolarRadiusAxis,
  RadialBar,
  RadialBarChart,
  Label as RechartsLabel,
} from "recharts";

function SectionSuspense({
  resource,
  children,
}: {
  resource: any;
  children: (data: ImageOptimizationApiResponse) => React.ReactNode;
}) {
  const data = resource.read();
  return children(data);
}

function createResource() {
  return wrapPromise(getImageOptimizationSettings());
}

const initialResource = createResource();

export function ImageOptimizationSettings() {
  const [resource, setResource] = useState(initialResource);
  const [saving, setSaving] = useState(false);

  // Refresh resource after save or action
  const refresh = useCallback(() => {
    setResource(createResource());
  }, []);

  return (
    <ErrorBoundary
      fallback={
        <div className="p-4 text-red-600">
          Error loading image optimization settings.
        </div>
      }
    >
      <Suspense fallback={<ImageOptimizationSettingsSkeleton />}>
        <SectionSuspense resource={resource}>
          {(initialData) => (
            <ImageOptimizationSettingsForm
              initialData={initialData}
              saving={saving}
              setSaving={setSaving}
              onSaved={refresh}
            />
          )}
        </SectionSuspense>
      </Suspense>
    </ErrorBoundary>
  );
}

function ImageOptimizationSettingsForm({
  initialData,
  saving,
  setSaving,
  onSaved,
}: {
  initialData: ImageOptimizationApiResponse;
  saving: boolean;
  setSaving: (v: boolean) => void;
  onSaved: () => void;
}) {
  const { settings: initial, server_capabilities: capabilities } = initialData;

  const [imageOptimizationLibrary, setImageOptimizationLibrary] = useState<
    TImageOptimizationSettings["image_optimization_library"]
  >(initial.image_optimization_library);
  const [imageOptimizeLosslessly, setImageOptimizeLosslessly] = useState(
    initial.image_optimize_losslessly
  );
  const [imageOptimizeOriginal, setImageOptimizeOriginal] = useState(
    initial.image_optimize_original
  );
  const [imageNextGenFormat, setImageNextGenFormat] = useState<
    TImageOptimizationSettings["image_next_gen_format"]
  >(initial.image_next_gen_format);
  const [imageQuality, setImageQuality] = useState([initial.image_quality]);
  const [imageDeliveryMethod, setImageDeliveryMethod] = useState<
    TImageOptimizationSettings["image_delivery_method"]
  >(initial.image_delivery_method);
  const [imageRemoveExif, setImageRemoveExif] = useState(
    initial.image_remove_exif
  );
  const [imageAutoResize, setImageAutoResize] = useState(
    initial.image_auto_resize
  );
  const [imageMaxWidth, setImageMaxWidth] = useState(
    String(initial.image_max_width)
  );
  const [imageMaxHeight, setImageMaxHeight] = useState(
    String(initial.image_max_height)
  );
  const [imageBatchProcessing, setImageBatchProcessing] = useState(
    initial.image_batch_processing
  );
  const [imageBatchSize, setImageBatchSize] = useState(
    String(initial.image_batch_size)
  );
  const [imageExcludeImages, setImageExcludeImages] = useState(
    initial.image_exclude_images
  );
  const [imageSelectedThumbnails, setImageSelectedThumbnails] = useState<
    string[]
  >(initial.image_selected_thumbnails);
  const [imageDisablePngGif, setImageDisablePngGif] = useState(
    initial.image_disable_png_gif
  );

  const [syncState, setSyncState] = useState(null);
  const [isSyncing, setIsSyncing] = useState(false);

  useEffect(() => {
    let interval: number;
    if (isSyncing) {
      interval = window.setInterval(async () => {
        try {
          const status = await getImageSyncStatus();
          setSyncState(status);
          if (status.is_finished) {
            setIsSyncing(false);
            sonnerToast.success("Bulk optimization complete!");
            onSaved(); // Refresh stats
          }
        } catch (error) {
          setIsSyncing(false);
          sonnerToast.error("Failed to get sync status.");
        }
      }, 5000); // Poll every 5 seconds
    }
    return () => clearInterval(interval);
  }, [isSyncing, onSaved]);

  const handleStartSync = async () => {
    // ** START: IMPROVED IMMEDIATE UX FEEDBACK **
    // Set syncing to true immediately to show the progress bar at 0%.
    setIsSyncing(true);
    setSyncState({
      processed: 0,
      total_to_optimize: initialData.stats.unoptimized_images,
      is_finished: false,
    });
    // ** END: IMPROVED IMMEDIATE UX FEEDBACK **

    try {
      const initialState = await startImageSync();
      // If the API confirms everything is already done, stop polling.
      if (initialState.is_finished) {
        setIsSyncing(false);
        sonnerToast.info("All images are already optimized.");
        onSaved();
      } else {
        // Otherwise, update the state with the real starting numbers and let the polling handle the rest.
        setSyncState(initialState);
      }
    } catch (error) {
      setIsSyncing(false); // Stop on error
      sonnerToast.error("Failed to start optimization process.");
    }
  };

  const handleCancelSync = async () => {
    setIsSyncing(false);
    try {
      await cancelImageSync();
      sonnerToast.success("Optimization cancelled.");
      onSaved(); // Refresh stats
    } catch (error) {
      sonnerToast.error("Failed to cancel optimization.");
    }
  };

  const currentLibrarySupports = useMemo(() => {
    if (imageOptimizationLibrary === "gd") {
      return {
        webp: capabilities.gd_webp_support,
        avif: capabilities.gd_avif_support,
      };
    }
    if (imageOptimizationLibrary === "imagemagick") {
      return {
        webp: capabilities.imagick_webp_support,
        avif: capabilities.imagick_avif_support,
      };
    }
    return { webp: false, avif: false };
  }, [imageOptimizationLibrary, capabilities]);

  const handleThumbnailToggle = (thumbnailId: string) => {
    setImageSelectedThumbnails((prev) =>
      prev.includes(thumbnailId)
        ? prev.filter((id) => id !== thumbnailId)
        : [...prev, thumbnailId]
    );
  };

  const handleSave = async () => {
    setSaving(true);
    const payload: Partial<TImageOptimizationSettings> = {
      image_optimization_library: imageOptimizationLibrary,
      image_optimize_losslessly: imageOptimizeLosslessly,
      image_optimize_original: imageOptimizeOriginal,
      image_next_gen_format: imageNextGenFormat,
      image_quality: imageQuality[0],
      image_delivery_method: imageDeliveryMethod,
      image_remove_exif: imageRemoveExif,
      image_auto_resize: imageAutoResize,
      image_max_width: Number(imageMaxWidth),
      image_max_height: Number(imageMaxHeight),
      image_batch_processing: imageBatchProcessing,
      image_batch_size: Number(imageBatchSize),
      image_exclude_images: imageExcludeImages,
      image_selected_thumbnails: imageSelectedThumbnails,
      image_disable_png_gif: imageDisablePngGif,
    };

    const savePromise = updateImageOptimizationSettings(payload)
      .then(() => {
        onSaved();
        return { name: "Settings" };
      })
      .catch((err) => {
        throw err;
      });

    sonnerToast.promise(savePromise, {
      loading: "Saving...",
      success: (data) => `${data.name} saved successfully.`,
      error: (err) => err.message || "Could not save settings.",
    });

    try {
      await savePromise;
    } finally {
      setSaving(false);
    }
  };

  const handleDestroy = () => {
    if (
      !window.confirm(
        "Are you sure you want to delete all optimized images and data? This action cannot be undone."
      )
    ) {
      return;
    }

    setSaving(true);
    const destroyPromise = destroyAllImageOptimizationData()
      .then((res) => {
        onSaved(); // Refresh data after deletion
        return res;
      })
      .catch((err) => {
        throw err;
      });

    sonnerToast.promise(destroyPromise, {
      loading: "Deleting optimization data...",
      success: (data) => data.message,
      error: (err) => err.message || "Could not delete data.",
    });

    setSaving(false);
  };

  const progressValue = useMemo(() => {
    if (!syncState || !syncState.total_to_optimize) return 0;
    return (syncState.processed / syncState.total_to_optimize) * 100;
  }, [syncState]);

  const chartData = useMemo(
    () => [
      {
        name: "optimized",
        value: initialData.stats.optimization_percent,
        fill: "hsl(var(--primary))",
      },
    ],
    [initialData.stats.optimization_percent]
  );

  // ** START: ACCURATE CHART ANGLE CALCULATION **
  // Calculate the end angle for the chart to accurately reflect the percentage.
  const chartEndAngle = useMemo(() => {
    const startAngle = 90;
    const percentage = chartData[0].value || 0;
    // A full circle is 360 degrees. We subtract from the start angle to draw clockwise.
    return startAngle - (percentage / 100) * 360;
  }, [chartData]);
  // ** END: ACCURATE CHART ANGLE CALCULATION **

  const chartConfig = {
    value: {
      label: "Optimized",
    },
    optimized: {
      label: "Optimized",
      color: "hsl(var(--primary))",
    },
  } satisfies ChartConfig;

  return (
    <TooltipProvider>
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <div className="lg:col-span-3 space-y-8">
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
                  value={imageOptimizationLibrary}
                  onValueChange={(value) =>
                    setImageOptimizationLibrary(
                      value as TImageOptimizationSettings["image_optimization_library"]
                    )
                  }
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Select optimization library" />
                  </SelectTrigger>
                  <SelectContent>
                    {capabilities.gd_support && (
                      <SelectItem value="gd">
                        <div className="flex items-center gap-2">
                          GD Library
                          {capabilities.gd_webp_support && (
                            <Badge variant="secondary">WebP</Badge>
                          )}
                          {capabilities.gd_avif_support && (
                            <Badge variant="secondary">AVIF</Badge>
                          )}
                        </div>
                      </SelectItem>
                    )}
                    {capabilities.imagick_support && (
                      <SelectItem value="imagemagick">
                        <div className="flex items-center gap-2">
                          ImageMagick
                          {capabilities.imagick_version && (
                            <Badge variant="secondary">
                              v{capabilities.imagick_version}
                            </Badge>
                          )}
                          {capabilities.imagick_webp_support && (
                            <Badge variant="secondary">WebP</Badge>
                          )}
                          {capabilities.imagick_avif_support && (
                            <Badge variant="secondary">AVIF</Badge>
                          )}
                        </div>
                      </SelectItem>
                    )}
                  </SelectContent>
                </Select>
                {imageOptimizationLibrary === "imagemagick" &&
                  capabilities.is_imagick_old && (
                    <div className="flex flex-col gap-2 p-4 border rounded-md bg-muted/40 mt-2">
                      <div className="flex items-center justify-between">
                        <Label htmlFor="disable-png-gif">
                          Disable Optimization For PNG, and GIF
                        </Label>
                        <Switch
                          id="disable-png-gif"
                          checked={imageDisablePngGif}
                          onCheckedChange={setImageDisablePngGif}
                        />
                      </div>
                      <div className="text-xs text-muted-foreground">
                        Your ImageMagick version is older than 7.x, which may
                        cause transparency issues with PNG/GIF files. It's
                        recommended to keep this enabled.
                      </div>
                    </div>
                  )}
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
              <div className="flex items-center justify-between">
                <Label htmlFor="optimize-original">
                  Optimize Original Images
                </Label>
                <Switch
                  id="optimize-original"
                  checked={imageOptimizeOriginal}
                  onCheckedChange={setImageOptimizeOriginal}
                />
              </div>
              <Separator />
              <div>
                <div className="mb-2 font-medium">
                  Thumbnail Sizes To Optimize
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {capabilities.thumbnail_sizes.map((thumbnail) => (
                    <div
                      key={thumbnail.id}
                      className="flex items-center space-x-2 p-2 border rounded-md hover:bg-accent hover:text-accent-foreground"
                    >
                      <Checkbox
                        id={thumbnail.id}
                        checked={imageSelectedThumbnails.includes(thumbnail.id)}
                        onCheckedChange={() =>
                          handleThumbnailToggle(thumbnail.id)
                        }
                      />
                      <Label
                        htmlFor={thumbnail.id}
                        className="text-sm font-normal cursor-pointer flex-grow"
                      >
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
              <Select
                value={imageNextGenFormat}
                onValueChange={(value) =>
                  setImageNextGenFormat(
                    value as TImageOptimizationSettings["image_next_gen_format"]
                  )
                }
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Select format" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="default">
                    Default (No Conversion)
                  </SelectItem>
                  {currentLibrarySupports.webp && (
                    <SelectItem value="webp">WebP</SelectItem>
                  )}
                  {currentLibrarySupports.avif && (
                    <SelectItem value="avif">AVIF</SelectItem>
                  )}
                </SelectContent>
              </Select>
              {!currentLibrarySupports.webp &&
                imageNextGenFormat === "webp" && (
                  <Alert variant="destructive" className="mt-4">
                    <AlertTitle>WebP Not Supported</AlertTitle>
                    <AlertDescription>
                      The selected library ({imageOptimizationLibrary}) does not
                      support WebP on your server. Please choose another format
                      or library.
                    </AlertDescription>
                  </Alert>
                )}
              {!currentLibrarySupports.avif &&
                imageNextGenFormat === "avif" && (
                  <Alert variant="destructive" className="mt-4">
                    <AlertTitle>AVIF Not Supported</AlertTitle>
                    <AlertDescription>
                      The selected library ({imageOptimizationLibrary}) does not
                      support AVIF on your server. Please choose another format
                      or library.
                    </AlertDescription>
                  </Alert>
                )}
            </CardContent>
          </Card>

          {/* Lossless Optimization */}
          <Card>
            <CardHeader>
              <CardTitle>Lossless Optimization</CardTitle>
              <CardDescription>
                Optimize images without losing quality. Disable to adjust
                quality manually.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex items-center justify-between">
                <Label htmlFor="lossless">Optimize Losslessly</Label>
                <Switch
                  id="lossless"
                  checked={imageOptimizeLosslessly}
                  onCheckedChange={setImageOptimizeLosslessly}
                />
              </div>

              {!imageOptimizeLosslessly && (
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
                value={imageDeliveryMethod}
                onValueChange={(value) =>
                  setImageDeliveryMethod(
                    value as TImageOptimizationSettings["image_delivery_method"]
                  )
                }
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
                  checked={imageRemoveExif}
                  onCheckedChange={setImageRemoveExif}
                />
              </div>

              <Separator />

              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label htmlFor="auto-resize">
                      Auto Resize Large Images
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Automatically resize images that exceed maximum dimensions
                    </p>
                  </div>
                  <Switch
                    id="auto-resize"
                    checked={imageAutoResize}
                    onCheckedChange={setImageAutoResize}
                  />
                </div>

                {imageAutoResize && (
                  <div className="grid grid-cols-2 gap-4 pl-4">
                    <div className="space-y-2">
                      <Label htmlFor="max-width">Maximum Width (px)</Label>
                      <Input
                        id="max-width"
                        type="number"
                        value={imageMaxWidth}
                        onChange={(e) => setImageMaxWidth(e.target.value)}
                        placeholder="1920"
                      />
                    </div>
                    <div className="space-y-2">
                      <Label htmlFor="max-height">Maximum Height (px)</Label>
                      <Input
                        id="max-height"
                        type="number"
                        value={imageMaxHeight}
                        onChange={(e) => setImageMaxHeight(e.target.value)}
                        placeholder="1080"
                      />
                    </div>
                  </div>
                )}
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
                value={imageExcludeImages}
                onChange={(e) => setImageExcludeImages(e.target.value)}
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
                  checked={imageBatchProcessing}
                  onCheckedChange={setImageBatchProcessing}
                />
              </div>

              {imageBatchProcessing && (
                <div className="space-y-2 pl-4">
                  <Label htmlFor="batch-size">Images Per Batch</Label>
                  <Input
                    id="batch-size"
                    type="number"
                    value={imageBatchSize}
                    onChange={(e) => setImageBatchSize(e.target.value)}
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
            <Button onClick={handleSave} disabled={saving || isSyncing}>
              {saving ? "Saving..." : "Save Settings"}
            </Button>
          </div>
        </div>
        <div className="lg:col-span-1">
          {/* Corrected: Sticky Wrapper for the Sidebar */}
          <div className="sticky top-6 space-y-8">
            {/* Manual Bulk Optimization Card */}
            <Card>
              <CardHeader>
                <CardTitle>Bulk Optimization</CardTitle>
                <CardDescription>
                  Manually process all unoptimized images in your Media Library.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex justify-center items-center">
                  <ChartContainer
                    config={chartConfig}
                    className="mx-auto aspect-square h-[150px]"
                  >
                    <RadialBarChart
                      data={chartData}
                      startAngle={90}
                      endAngle={chartEndAngle} // Use the dynamic end angle
                      innerRadius={60}
                      outerRadius={80}
                      barSize={20}
                    >
                      <PolarGrid
                        gridType="circle"
                        radialLines={false}
                        stroke="none"
                        className="first:fill-muted last:fill-background"
                      />
                      <RadialBar dataKey="value" background cornerRadius={10} />
                      <PolarRadiusAxis
                        tick={false}
                        tickLine={false}
                        axisLine={false}
                        domain={[0, 100]}
                      >
                        <RechartsLabel
                          content={({ viewBox }) => {
                            if (viewBox && "cx" in viewBox && "cy" in viewBox) {
                              return (
                                <text
                                  x={viewBox.cx}
                                  y={viewBox.cy}
                                  textAnchor="middle"
                                  dominantBaseline="middle"
                                >
                                  <tspan
                                    x={viewBox.cx}
                                    y={viewBox.cy}
                                    className="fill-foreground text-2xl font-bold"
                                  >
                                    {`${chartData[0].value.toFixed(1)}%`}
                                  </tspan>
                                </text>
                              );
                            }
                          }}
                        />
                      </PolarRadiusAxis>
                    </RadialBarChart>
                  </ChartContainer>
                </div>
                <div className="text-center text-sm text-muted-foreground">
                  {initialData.stats.optimized_images} /{" "}
                  {initialData.stats.total_images} images optimized
                </div>
                {isSyncing ? (
                  <div className="space-y-2">
                    <Progress value={progressValue} />
                    <p className="text-xs text-center text-muted-foreground">
                      Processing... ({syncState?.processed || 0} /{" "}
                      {syncState?.total_to_optimize || 0})
                    </p>
                    <Button
                      variant="outline"
                      onClick={handleCancelSync}
                      className="w-full"
                    >
                      Cancel Optimization
                    </Button>
                  </div>
                ) : (
                  <Button
                    onClick={handleStartSync}
                    className="w-full"
                    disabled={
                      saving || initialData.stats.unoptimized_images === 0
                    }
                  >
                    Optimize All Unoptimized Images
                  </Button>
                )}
              </CardContent>
            </Card>

            {/* Danger Zone Card */}
            <Card className="border-destructive">
              <CardHeader>
                <CardTitle className="text-destructive">Danger Zone</CardTitle>
                <CardDescription>
                  Destructive actions that cannot be undone.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label className="font-semibold">
                    Destroy All Optimization Data
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    This will permanently delete all generated WebP/AVIF files
                    and remove all optimization records from your database.
                    Original images will not be affected.
                  </p>
                </div>
                <Button
                  variant="destructive"
                  onClick={handleDestroy}
                  disabled={saving || isSyncing}
                  className="w-full"
                >
                  <Trash2Icon className="mr-2 h-4 w-4" />
                  Destroy All Data
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </TooltipProvider>
  );
}
