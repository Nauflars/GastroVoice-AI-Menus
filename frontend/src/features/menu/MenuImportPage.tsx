import { useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { uploadMenuImage, confirmMenuImport } from './api'
import type { MenuImportPreview } from './types'
import { MENU_QUERY_KEY } from './useMenu'

type Step = 'upload' | 'analyzing' | 'preview' | 'confirming' | 'done' | 'error'

export default function MenuImportPage() {
  const [step, setStep] = useState<Step>('upload')
  const [preview, setPreview] = useState<MenuImportPreview | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const [dragOver, setDragOver] = useState(false)
  const [selectedFile, setSelectedFile] = useState<File | null>(null)
  const [imagePreviewUrl, setImagePreviewUrl] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const qc = useQueryClient()

  const handleFile = (file: File) => {
    if (!file.type.startsWith('image/')) {
      setErrorMsg('Please upload an image file (JPG, PNG, WEBP).')
      return
    }
    setSelectedFile(file)
    setImagePreviewUrl(URL.createObjectURL(file))
    setErrorMsg(null)
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setDragOver(false)
    const file = e.dataTransfer.files[0]
    if (file != null) handleFile(file)
  }

  const handleAnalyze = async () => {
    if (selectedFile == null) return
    setStep('analyzing')
    setErrorMsg(null)
    try {
      const result = await uploadMenuImage(selectedFile)
      setPreview(result)
      setStep('preview')
    } catch {
      setErrorMsg('Failed to analyze the menu image. Please try again.')
      setStep('error')
    }
  }

  const handleConfirm = async () => {
    if (preview == null) return
    setStep('confirming')
    try {
      await confirmMenuImport(preview.previewId)
      await qc.invalidateQueries({ queryKey: MENU_QUERY_KEY })
      setStep('done')
    } catch {
      setErrorMsg('Failed to save the menu. Please try again.')
      setStep('error')
    }
  }

  const reset = () => {
    setStep('upload')
    setPreview(null)
    setSelectedFile(null)
    setImagePreviewUrl(null)
    setErrorMsg(null)
  }

  // Count totals for summary
  const totalCategories = preview?.categories.length ?? 0
  const totalItems = preview?.categories.reduce((acc, c) => acc + c.items.length, 0) ?? 0

  return (
    <div className="max-w-3xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">🤖 AI Menu Import</h1>
        <p className="text-gray-500 mt-1">
          Upload a photo of your menu and our AI will automatically extract all categories and dishes.
        </p>
      </div>

      {/* Step: Upload */}
      {(step === 'upload' || step === 'error') && (
        <div className="space-y-4">
          {/* Dropzone */}
          <div
            onDrop={handleDrop}
            onDragOver={(e) => { e.preventDefault(); setDragOver(true) }}
            onDragLeave={() => setDragOver(false)}
            onClick={() => fileInputRef.current?.click()}
            className={`border-2 border-dashed rounded-xl p-12 text-center cursor-pointer transition-colors ${
              dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400 hover:bg-gray-50'
            }`}
          >
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={(e) => { const f = e.target.files?.[0]; if (f != null) handleFile(f) }}
            />
            <p className="text-4xl mb-3">📸</p>
            <p className="text-gray-700 font-medium">Drop your menu image here</p>
            <p className="text-sm text-gray-400 mt-1">or click to browse — JPG, PNG, WEBP up to 20MB</p>
          </div>

          {/* Preview of selected image */}
          {imagePreviewUrl != null && selectedFile != null && (
            <div className="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
              <img
                src={imagePreviewUrl}
                alt="Menu preview"
                className="w-20 h-20 object-cover rounded-lg border border-gray-200"
              />
              <div className="flex-1">
                <p className="font-medium text-gray-900">{selectedFile.name}</p>
                <p className="text-sm text-gray-400">{(selectedFile.size / 1024).toFixed(1)} KB</p>
              </div>
              <button
                onClick={(e) => { e.stopPropagation(); reset() }}
                className="text-sm text-red-500 hover:text-red-700"
              >
                Remove
              </button>
            </div>
          )}

          {errorMsg != null && (
            <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded px-4 py-2">
              {errorMsg}
            </p>
          )}

          <button
            onClick={() => { void handleAnalyze() }}
            disabled={selectedFile == null}
            className="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-40 transition-colors"
          >
            Analyze with AI
          </button>
        </div>
      )}

      {/* Step: Analyzing */}
      {step === 'analyzing' && (
        <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <div className="text-5xl animate-pulse mb-4">🤖</div>
          <p className="text-lg font-medium text-gray-800">Analyzing your menu…</p>
          <p className="text-sm text-gray-400 mt-2">
            GPT-4 Vision is reading your menu image and extracting all dishes. This may take a moment.
          </p>
        </div>
      )}

      {/* Step: Preview — AI analysis results */}
      {step === 'preview' && preview != null && (
        <div className="space-y-6">
          {/* Summary banner */}
          <div className="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
            <span className="text-2xl">✅</span>
            <div>
              <p className="font-semibold text-green-800">Analysis complete!</p>
              <p className="text-sm text-green-700">
                Found <strong>{totalCategories}</strong> categories and <strong>{totalItems}</strong> dishes.
                Review the results below and confirm to save.
              </p>
            </div>
          </div>

          {/* Image + results side by side */}
          <div className="grid grid-cols-5 gap-6">
            {imagePreviewUrl != null && (
              <div className="col-span-2">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Original Image</p>
                <img
                  src={imagePreviewUrl}
                  alt="Uploaded menu"
                  className="w-full rounded-xl border border-gray-200 object-contain max-h-96"
                />
              </div>
            )}

            <div className={imagePreviewUrl != null ? 'col-span-3' : 'col-span-5'}>
              <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Extracted Menu</p>
              <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
                {preview.categories.map((cat, ci) => (
                  <div key={ci} className="bg-white rounded-xl border border-gray-200">
                    <div className="px-4 py-3 border-b border-gray-100 bg-gray-50 rounded-t-xl">
                      <p className="font-semibold text-gray-800">{cat.name}</p>
                      <p className="text-xs text-gray-400">{cat.items.length} items</p>
                    </div>
                    <div className="divide-y divide-gray-100">
                      {cat.items.map((item, ii) => (
                        <div key={ii} className="px-4 py-3 flex justify-between items-start gap-3">
                          <div>
                            <p className="font-medium text-gray-900 text-sm">{item.name}</p>
                            {item.description != null && (
                              <p className="text-xs text-gray-500 mt-0.5">{item.description}</p>
                            )}
                            {item.allergens.length > 0 && (
                              <p className="text-xs text-amber-600 mt-0.5">
                                ⚠ {item.allergens.join(', ')}
                              </p>
                            )}
                          </div>
                          <p className="text-sm font-semibold text-gray-800 whitespace-nowrap">
                            {item.price.toFixed(2)} {item.currency}
                          </p>
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="flex gap-3">
            <button
              onClick={() => { void handleConfirm() }}
              className="bg-green-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-green-700 transition-colors"
            >
              ✓ Confirm & Save Menu
            </button>
            <button
              onClick={reset}
              className="border border-gray-300 text-gray-700 px-6 py-2.5 rounded-lg font-medium hover:bg-gray-50 transition-colors"
            >
              Upload Different Image
            </button>
          </div>
        </div>
      )}

      {/* Step: Confirming */}
      {step === 'confirming' && (
        <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <div className="text-5xl mb-4">💾</div>
          <p className="text-lg font-medium text-gray-800">Saving your menu…</p>
        </div>
      )}

      {/* Step: Done */}
      {step === 'done' && (
        <div className="bg-white rounded-xl border border-green-200 p-12 text-center">
          <div className="text-5xl mb-4">🎉</div>
          <p className="text-2xl font-bold text-green-800 mb-2">Menu saved successfully!</p>
          <p className="text-gray-500 mb-6">
            {totalCategories} categories and {totalItems} dishes are now available.
          </p>
          <div className="flex justify-center gap-3">
            <a
              href="/menu"
              className="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700 transition-colors"
            >
              View Menu
            </a>
            <button
              onClick={reset}
              className="border border-gray-300 text-gray-700 px-6 py-2.5 rounded-lg font-medium hover:bg-gray-50 transition-colors"
            >
              Import Another
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
