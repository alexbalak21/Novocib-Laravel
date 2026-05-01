@extends('layouts.app')

@section('title', 'Novocib Product Catalog - Enzymes, Assay Kits & Analytical Services')

@section('meta')
<meta name="description" content="Explore Novocib's complete catalog of high-purity enzymes, enzymatic assay kits, nucleotide analysis services, and recombinant proteins for biochemical and pharmaceutical research.">
<meta name="keywords" content="enzyme assay kits, nucleotide metabolism, purified enzymes, PRPP-S assay, HPRT assay, IMPDH2, dCK, ADK, nucleotide analysis, Novocib">

@verbatim
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Active Purified Enzymes for Nucleotide Metabolism Research",
    "description": "High-purity enzymes for nucleotide metabolism research, drug discovery, and biopharmaceutical development.",
    "url": "https://www.novocib.com/active-purified-enzymes",
    "publisher": {
        "@type": "Organization",
        "name": "NOVOCIB",
        "logo": {
            "@type": "ImageObject",
            "url": "https://www.novocib.com/app/img/logo.png"
        }
    },
    "mainEntity": [{
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "What are the main applications of NOVOCIB's purified enzymes?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Our enzymes are widely used in drug discovery, antiviral and anticancer research, nucleotide metabolism studies, and biochemical screening assays."
                }
            },
            {
                "@type": "Question",
                "name": "Are these enzymes suitable for high-throughput screening?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Yes, our enzymes are provided in lyophilized form with high purity and activity, making them ideal for high-throughput screening applications."
                }
            },
            {
                "@type": "Question",
                "name": "What quality controls are performed on the enzymes?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Each enzyme batch undergoes rigorous quality control including activity assays, purity assessment by SDS-PAGE, and endotoxin testing."
                }
            }
        ]
    }]
}
</script>
@endverbatim
@endsection

@section('content')

<style>
  .btn-novo {
    background-color: var(--novo-blue) !important;
    color: white !important;
  }
  .btn-novo:hover {
    background-color: #2e5fa0 !important;
    color: white !important;
  }
</style>

<div class="text-center pt-5">
    <h1>Catalog</h1>
</div>

@endsection
