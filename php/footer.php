</div>
<!-- Footer -->
<?php include_once("data.php"); ?>
<footer class="py-4 mt-5 text-center text-muted">
    <p>
        Songbook Manager &copy; 2025 | 
        <em><?php echo VERSION; ?></em>
    </p>
</footer>

<style>
    /* Make only the About link in the footer less contrasting */
    .footer-about {
        color: #777;        /* subtle gray */
        text-decoration: none; /* remove underline if needed */
        font-weight: normal;   /* optional, keep normal weight */
    }
    .footer-about:hover {
        color: #555;        /* slightly darker on hover */
        text-decoration: underline; /* subtle hover effect */
    }
</style>

</body>
</html>
