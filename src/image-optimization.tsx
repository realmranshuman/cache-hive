import * as React from "@wordpress/element";
import {
  useState,
  Suspense,
  useCallback,
  useMemo,
  useEffect,
  useRef,
} from "@wordpress/element";
import type { ReactNode } from "react";
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
import { InfoIcon, Trash2Icon, PlayIcon, SquareIcon } from "lucide-react";
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
  revertOptimizationData,
  startImageSync,
  getImageSyncStatus,
  cancelImageSync,
  ImageOptimizationApiResponse,
  ImageOptimizationSettings as TImageOptimizationSettings,
  ImageStats,
  SyncState,
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
  children: (data: ImageOptimizationApiResponse) => ReactNode;
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
  const {
    settings: initial,
    server_capabilities: capabilities,
    sync_state: initialSyncState,
  } = initialData;

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
  const [imageCronOptimization, setImageCronOptimization] = useState(
    initial.image_cron_optimization
  );
  const [imageExcludeImages, setImageExcludeImages] = useState(
    initial.image_exclude_images
  );
  const [imageExcludePictureRewrite, setImageExcludePictureRewrite] = useState(
    initial.image_exclude_picture_rewrite
  );
  const [imageSelectedThumbnails, setImageSelectedThumbnails] = useState<
    string[]
  >(initial.image_selected_thumbnails);
  const [imageDisablePngGif, setImageDisablePngGif] = useState(
    initial.image_disable_png_gif
  );

  const [stats, setStats] = useState<ImageStats>(initialData.stats);
  const [syncState, setSyncState] = useState<SyncState | false>(
    initialSyncState
  );
  const [isSyncing, setIsSyncing] = useState(
    initialSyncState && initialSyncState.is_running
  );

  const isSyncingRef = useRef(isSyncing);
  useEffect(() => {
    isSyncingRef.current = isSyncing;
  }, [isSyncing]);

  useEffect(() => {
    setStats(initialData.stats);
    const currentSyncState = initialData.sync_state;
    setSyncState(currentSyncState);
    if (currentSyncState && currentSyncState.is_running && !isSyncing) {
      setIsSyncing(true);
    }
  }, [initialData]);

  const processNextImage = useCallback(async () => {
    if (!isSyncingRef.current) return;

    try {
      const newStatus = await getImageSyncStatus();
      setSyncState(newStatus);

      if (newStatus.is_finished) {
        setIsSyncing(false);
        sonnerToast.success("Bulk optimization complete!");
        getImageOptimizationSettings()
          .then((data) => setStats(data.stats))
          .catch(() =>
            sonnerToast.error("Could not refresh final stats automatically.")
          );
      } else {
        setTimeout(processNextImage, 300); // Short delay between requests to be nice to the server
      }
    } catch (error) {
      setIsSyncing(false);
      sonnerToast.error(
        "Optimization stopped due to an error. You can resume it at any time."
      );
    }
  }, []);

  useEffect(() => {
    if (isSyncing) {
      processNextImage();
    }
  }, [isSyncing, processNextImage]);

  const handleStartSync = async () => {
    if (imageNextGenFormat === "default") {
      sonnerToast.error("Please select a format (WebP or AVIF) to optimize.");
      return;
    }
    try {
      const initialState = await startImageSync(imageNextGenFormat);
      setSyncState(initialState);
      if (initialState.is_finished) {
        sonnerToast.info("All images are already optimized for this format.");
        onSaved();
      } else {
        setIsSyncing(true);
      }
    } catch (error) {
      sonnerToast.error("Failed to start optimization process.");
    }
  };

  const handleCancelSync = async () => {
    setIsSyncing(false); // This will stop the loop via the ref
    try {
      await cancelImageSync();
      sonnerToast.info("Optimization process has been paused.");
      onSaved(); // Refresh to show the latest stats and paused state.
    } catch (error) {
      sonnerToast.error("Failed to cancel optimization process.");
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
      image_cron_optimization: imageCronOptimization,
      image_exclude_images: imageExcludeImages,
      image_exclude_picture_rewrite: imageExcludePictureRewrite,
      image_selected_thumbnails: imageSelectedThumbnails,
      image_disable_png_gif: imageDisablePngGif,
    };

    const savePromise = updateImageOptimizationSettings(payload)
      .then((data) => {
        setStats(data.stats);
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

  const handleRevert = async (format?: "webp" | "avif") => {
    let confirmationText =
      "Are you sure? This will remove all optimization data and generated files, and cannot be undone.";
    if (format) {
      confirmationText = `Are you sure? This will remove all ${format.toUpperCase()} files and optimization data. This cannot be undone.`;
    }

    if (!window.confirm(confirmationText)) {
      return;
    }

    setSaving(true);
    const revertPromise = revertOptimizationData(format);

    sonnerToast.promise(revertPromise, {
      loading: "Reverting optimization data...",
      success: (data) => data.message,
      error: (err) => err.message || "Could not revert data.",
    });

    try {
      const res = await revertPromise;
      setStats(res.stats); // Instantly update the UI with the new stats
    } catch (error) {
      // The toast will handle showing the error message.
    } finally {
      setSaving(false); // Ensures the loading state is always turned off.
    }
  };

  const displayedStats =
    stats && imageNextGenFormat !== "default"
      ? stats[imageNextGenFormat]
      : null;

  const progressValue = useMemo(() => {
    if (!syncState || !syncState.total_to_optimize) return 0;
    return (syncState.processed / syncState.total_to_optimize) * 100;
  }, [syncState]);

  const chartData = useMemo(() => {
    const percent = displayedStats ? displayedStats.optimization_percent : 0;
    return [
      {
        name: "optimized",
        value: percent,
        fill: "hsl(var(--primary))",
      },
    ];
  }, [displayedStats]);

  const chartEndAngle = useMemo(() => {
    const startAngle = 90;
    const percentage = chartData[0].value || 0;
    return startAngle - (percentage / 100) * 360;
  }, [chartData]);

  const chartConfig = {
    value: {
      label: "Optimized",
    },
    optimized: {
      label: "Optimized",
      color: "hsl(var(--primary))",
    },
  } satisfies ChartConfig;

  const syncButtonText =
    syncState && syncState.processed > 0
      ? "Resume Optimization"
      : `Optimize All to ${
          imageNextGenFormat !== "default"
            ? imageNextGenFormat.toUpperCase()
            : ""
        }`;

  return (
    <TooltipProvider>
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <div className="lg:col-span-3 space-y-8">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                Optimization Library
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
                configuration. This choice may affect which next-gen formats are
                available.
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

          <Card>
            <CardHeader>
              <CardTitle>Optimization Targets</CardTitle>
              <CardDescription>
                Choose which original images and thumbnail sizes should be
                optimized by the selected library.
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

          <Card>
            <CardHeader>
              <CardTitle>Format & Quality Settings</CardTitle>
              <CardDescription>
                Configure next-gen image formats and compression levels to
                balance file size and visual quality.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-2">
                <Label>Serve Next-Gen Formats</Label>
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
                        The selected library ({imageOptimizationLibrary}) does
                        not support WebP on your server. Please choose another
                        format or library.
                      </AlertDescription>
                    </Alert>
                  )}
                {!currentLibrarySupports.avif &&
                  imageNextGenFormat === "avif" && (
                    <Alert variant="destructive" className="mt-4">
                      <AlertTitle>AVIF Not Supported</AlertTitle>
                      <AlertDescription>
                        The selected library ({imageOptimizationLibrary}) does
                        not support AVIF on your server. Please choose another
                        format or library.
                      </AlertDescription>
                    </Alert>
                  )}
              </div>

              <Separator />

              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label htmlFor="lossless">Optimize Losslessly</Label>
                    <p className="text-xs text-muted-foreground">
                      Optimize images without losing quality. Disable to adjust
                      quality manually.
                    </p>
                  </div>
                  <Switch
                    id="lossless"
                    checked={imageOptimizeLosslessly}
                    onCheckedChange={setImageOptimizeLosslessly}
                  />
                </div>

                {!imageOptimizeLosslessly && (
                  <div className="space-y-4 pt-4 border-t">
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
                      Lower values create smaller files but may reduce image
                      quality. Recommended: 70-90%.
                    </p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Advanced Settings</CardTitle>
              <CardDescription>
                Configure image delivery, metadata, and resizing options.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-2">
                <Label>Image Delivery Method</Label>
                <RadioGroup
                  value={imageDeliveryMethod}
                  onValueChange={(value) =>
                    setImageDeliveryMethod(
                      value as TImageOptimizationSettings["image_delivery_method"]
                    )
                  }
                  className="mt-2"
                >
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="rewrite" id="rewrite" />
                    <Label htmlFor="rewrite">Server Rewrite</Label>
                  </div>
                  <div className="flex items-center space-x-2">
                    <RadioGroupItem value="picture" id="picture" />
                    <Label htmlFor="picture">Use &lt;picture&gt; tag</Label>
                  </div>
                </RadioGroup>
              </div>

              <Separator />

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="remove-exif">Remove EXIF Data</Label>
                  <p className="text-sm text-muted-foreground">
                    Strips metadata (e.g., camera settings) to reduce file size.
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
                      Auto-Resize Large Images
                    </Label>
                    <p className="text-sm text-muted-foreground">
                      Automatically downscale images that exceed maximum
                      dimensions upon upload.
                    </p>
                  </div>
                  <Switch
                    id="auto-resize"
                    checked={imageAutoResize}
                    onCheckedChange={setImageAutoResize}
                  />
                </div>

                {imageAutoResize && (
                  <div className="grid grid-cols-2 gap-4 pl-4 pt-4 border-t">
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

          <Card>
            <CardHeader>
              <CardTitle>Exclusions</CardTitle>
              <CardDescription>
                Prevent specific images from being optimized or rewritten. Add
                one rule per line.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="space-y-2">
                <Label htmlFor="exclude-images">
                  Exclude Images From Optimization (by URL)
                </Label>
                <Textarea
                  id="exclude-images"
                  value={imageExcludeImages}
                  onChange={(e) => setImageExcludeImages(e.target.value)}
                  placeholder="logo.png&#10;/uploads/2024/do-not-touch.jpg"
                  rows={4}
                />
                <p className="text-xs text-muted-foreground">
                  Use partial or full URLs. Prevents WebP/AVIF file creation.
                </p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="exclude-rewrite">
                  Exclude Images From &lt;picture&gt; Tag Rewrite (by CSS
                  Selector)
                </Label>
                <Textarea
                  id="exclude-rewrite"
                  value={imageExcludePictureRewrite}
                  onChange={(e) =>
                    setImageExcludePictureRewrite(e.target.value)
                  }
                  placeholder="div.profile-card&#10;.slider-image&#10;#main-logo"
                  rows={4}
                  disabled={imageDeliveryMethod !== "picture"}
                />
                {imageDeliveryMethod !== "picture" && (
                  <p className="text-xs text-muted-foreground">
                    This option is only available when the "Use &lt;picture&gt;
                    tag" delivery method is selected.
                  </p>
                )}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Automatic Background Optimization</CardTitle>
              <CardDescription>
                Enable background processing via server cron jobs to
                automatically optimize new uploads.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label htmlFor="cron-optimization">
                    Enable Automatic Optimization
                  </Label>
                  <p className="text-sm text-muted-foreground">
                    Recommended for most sites. Images are processed in the
                    background using WP-Cron.
                  </p>
                </div>
                <Switch
                  id="cron-optimization"
                  checked={imageCronOptimization}
                  onCheckedChange={setImageCronOptimization}
                />
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button onClick={handleSave} disabled={saving || isSyncing}>
              {saving ? "Saving..." : "Save Settings"}
            </Button>
          </div>
        </div>

        <div className="lg:col-span-1">
          <div className="sticky top-6 space-y-8">
            <Card>
              <CardHeader>
                <CardTitle>Manual Bulk Optimization</CardTitle>
                <CardDescription>
                  Manually process images for the selected format.
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
                      endAngle={chartEndAngle}
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
                  {displayedStats
                    ? `${displayedStats.optimized_count} / ${
                        stats.total_images
                      } images optimized to ${imageNextGenFormat.toUpperCase()}`
                    : "Select a format to see stats"}
                </div>
                {isSyncing && syncState ? (
                  <div className="space-y-2">
                    <Progress value={progressValue} />
                    <p className="text-xs text-center text-muted-foreground">
                      Processing... ({syncState.processed || 0} /{" "}
                      {syncState.total_to_optimize || 0})
                    </p>
                    <Button
                      variant="outline"
                      onClick={handleCancelSync}
                      className="w-full"
                    >
                      <SquareIcon className="mr-2 h-4 w-4" />
                      Pause Optimization
                    </Button>
                  </div>
                ) : (
                  <Button
                    onClick={handleStartSync}
                    className="w-full"
                    disabled={
                      saving ||
                      imageNextGenFormat === "default" ||
                      (displayedStats &&
                        displayedStats.unoptimized_images === 0)
                    }
                  >
                    <PlayIcon className="mr-2 h-4 w-4" />
                    {syncButtonText}
                  </Button>
                )}
              </CardContent>
            </Card>

            <Card className="border-destructive">
              <CardHeader>
                <CardTitle className="text-destructive">Danger Zone</CardTitle>
                <CardDescription>
                  These actions will remove generated files and cannot be
                  undone.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <Button
                  variant="destructive"
                  onClick={() => handleRevert("webp")}
                  disabled={saving || isSyncing}
                  className="w-full"
                >
                  <Trash2Icon className="mr-2 h-4 w-4" />
                  Revert WebP Images
                </Button>
                <Button
                  variant="destructive"
                  onClick={() => handleRevert("avif")}
                  disabled={saving || isSyncing}
                  className="w-full"
                >
                  <Trash2Icon className="mr-2 h-4 w-4" />
                  Revert AVIF Images
                </Button>
                <Separator className="my-2" />
                <Button
                  variant="destructive"
                  onClick={() => handleRevert()}
                  disabled={saving || isSyncing}
                  className="w-full"
                >
                  <Trash2Icon className="mr-2 h-4 w-4" />
                  Revert All Optimizations
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </TooltipProvider>
  );
}
