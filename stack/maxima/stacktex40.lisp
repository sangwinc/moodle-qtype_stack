;; Back compatibility with versions of Maxima prior to Maxima 5.41.0
;; Chris Sangwin 26 Nov 2017.
;;
;; These all involve the change from the old (getcharn f) to (get-first-char).

;; Note, this commit in Maxmia changed (getcharn f) to (get-first-char).
;; https://sourceforge.net/p/maxima/code/ci/b27acfa194281f42ef6d2a4ef2434d8dea4705f1/


;; insert left-angle-brackets for mncexpt. a^<n> is how a^^n looks.
(defun tex-mexpt (x l r)
  (let((nc (eq (caar x) 'mncexpt))) ; true if a^^b rather than a^b
    ;; here is where we have to check for f(x)^b to be displayed
    ;; as f^b(x), as is the case for sin(x)^2 .
    ;; which should be sin^2 x rather than (sin x)^2 or (sin(x))^2.
    ;; yet we must not display (a+b)^2 as +^2(a,b)...
    ;; or (sin(x))^(-1) as sin^(-1)x, which would be arcsine x
    (cond ;; this whole clause
      ;; should be deleted if this hack is unwanted and/or the
      ;; time it takes is of concern.
      ;; it shouldn't be too expensive.
      ((and (eq (caar x) 'mexpt)      ; don't do this hack for mncexpt
            (let*
                ((fx (cadr x)) ; this is f(x)
                 (f (and (not (atom fx)) (atom (caar fx)) (caar fx))) ; this is f [or nil]
                 (bascdr (and f (cdr fx))) ; this is (x) [maybe (x,y..), or nil]
                 (expon (caddr x)) ;; this is the exponent
                 (doit (and
                        f ; there is such a function
                        (member (getcharn f 1) '(#\% #\$)) ;; insist it is a % or $ function
                        (not (member 'array (cdar fx) :test #'eq)) ; fix for x[i]^2
                        (not (member f '(%sum %product %derivative %integrate %at $texsub
                                         %lsum %limit $pderivop $+-) :test #'eq)) ;; what else? what a hack...
                        (or (and (atom expon) (not (numberp expon))) ; f(x)^y is ok
                            (and (atom expon) (numberp expon) (> expon 0))))))
                                        ; f(x)^3 is ok, but not f(x)^-1, which could
                                        ; inverse of f, if written f^-1 x
                                        ; what else? f(x)^(1/2) is sqrt(f(x)), ??
              (cond (doit
                     (setq l (tex `((mexpt) ,f ,expon) l nil 'mparen 'mparen))
                     (if (and (null (cdr bascdr))
                              (eq (get f 'tex) 'tex-prefix))
                         (setq r (tex (car bascdr) nil r f 'mparen))
                         (setq r (tex (cons '(mprogn) bascdr) nil r 'mparen 'mparen))))
                    (t nil))))) ; won't doit. fall through
      (t (setq l (cond ((or ($bfloatp (cadr x))
                            (and (numberp (cadr x)) (numneedsparen (cadr x))))
                        ; ACTUALLY THIS TREATMENT IS NEEDED WHENEVER (CAAR X) HAS GREATER BINDING POWER THAN MTIMES ...
                        (tex (cadr x) (append l '("\\left(")) '("\\right)") lop (caar x)))
                       (t (tex (cadr x) l nil lop (caar x))))
               r (if (mmminusp (setq x (nformat (caddr x))))
                     ;; the change in base-line makes parens unnecessary
                     (if nc
                         (tex (cadr x) '("^ {-\\langle ") (cons "\\rangle }" r) 'mparen 'mparen)
                         (tex (cadr x) '("^ {- ") (cons " }" r) 'mminus 'mparen))
                     (if nc
                         (tex x (list "^{\\langle ") (cons "\\rangle}" r) 'mparen 'mparen)
                         (if (and (integerp x) (< x 10))
                             (tex x (list "^")(cons "" r) 'mparen 'mparen)
                             (tex x (list "^{")(cons "}" r) 'mparen 'mparen)))))))
    (append l r)))

;; *************************************************************************************************
;; Added 2020-01-09
;; Fix sconcat on versions of Maxima (GCL) prior to 5.41.0
;; See https://sourceforge.net/p/maxima/code/ci/a7de72db1669deec775dfab6159eb8ca4357b998/

;; $sconcat for lists
;;
;;   optional: insert a user defined delimiter string
;; 
(defun $simplode (li &optional (ds ""))
  (unless (listp li)
    (gf-merror (intl:gettext "`simplode': first argument must be a list.")) )
  (unless (stringp ds) 
    (s-error1 "simplode" "optional second") )
  (setq li (cdr li))
  (cond 
    ((null li)
      ($sconcat) )
    ((null (cdr li))
      ($sconcat (car li)) )
    ((string= ds "")
      (reduce #'$sconcat li) )
    (t
      (do (acc) (())
        (push ($sconcat (pop li)) acc)
        (when (null li)
          (return (reduce #'(lambda (s0 s1) (concatenate 'string s0 s1)) (nreverse acc) :initial-value "")))
        (push ds acc) ))))


